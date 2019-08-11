/*******************************************************************************************

            Hash(BEGIN(Satoshi[2010]), END(Sunny[2012])) == Videlicet[2014] ++

 [Learn and Create] Viz. http://www.opensource.org/licenses/mit-license.php

*******************************************************************************************/

#ifndef NEXUS_UINT1024_H
#define NEXUS_UINT1024_H

#include <limits.h>
#include <stdio.h>
#include <string.h>
#include <string>
#include <vector>
#include <stdint.h>

/** Linux Specific Work Around (For Now). **/
#if defined(MAC_OSX) || defined(WIN32)
typedef int64_t int64;
typedef uint64_t uint64;
#else
typedef long long  int64;
typedef unsigned long long  uint64;
#endif


/** Base class without constructors for uint256, uint512, uint576, uint1024.
 * This makes the compiler let u use it in a union.
 */
template<unsigned int BITS>
class base_uint
{
protected:
    enum { WIDTH=BITS/32 };
    unsigned int pn[WIDTH];
public:

    bool operator!() const
    {
        for (int i = 0; i < WIDTH; i++)
            if (pn[i] != 0)
                return false;
        return true;
    }

    const base_uint operator~() const
    {
        base_uint ret;
        for (int i = 0; i < WIDTH; i++)
            ret.pn[i] = ~pn[i];
        return ret;
    }

    const base_uint operator-() const
    {
        base_uint ret;
        for (int i = 0; i < WIDTH; i++)
            ret.pn[i] = ~pn[i];
        ret++;
        return ret;
    }


    base_uint& operator=(uint64 b)
    {
        pn[0] = (unsigned int)b;
        pn[1] = (unsigned int)(b >> 32);
        for (int i = 2; i < WIDTH; i++)
            pn[i] = 0;
        return *this;
    }

    base_uint& operator^=(const base_uint& b)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] ^= b.pn[i];
        return *this;
    }

    base_uint& operator&=(const base_uint& b)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] &= b.pn[i];
        return *this;
    }

    base_uint& operator|=(const base_uint& b)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] |= b.pn[i];
        return *this;
    }

    base_uint& operator^=(uint64 b)
    {
        pn[0] ^= (unsigned int)b;
        pn[1] ^= (unsigned int)(b >> 32);
        return *this;
    }

    base_uint& operator|=(uint64 b)
    {
        pn[0] |= (unsigned int)b;
        pn[1] |= (unsigned int)(b >> 32);
        return *this;
    }

    base_uint& operator<<=(unsigned int shift)
    {
        base_uint a(*this);
        for (int i = 0; i < WIDTH; i++)
            pn[i] = 0;
        int k = shift / 32;
        shift = shift % 32;
        for (int i = 0; i < WIDTH; i++)
        {
            if (i+k+1 < WIDTH && shift != 0)
                pn[i+k+1] |= (a.pn[i] >> (32-shift));
            if (i+k < WIDTH)
                pn[i+k] |= (a.pn[i] << shift);
        }
        return *this;
    }

    base_uint& operator>>=(unsigned int shift)
    {
        base_uint a(*this);
        for (int i = 0; i < WIDTH; i++)
            pn[i] = 0;
        int k = shift / 32;
        shift = shift % 32;
        for (int i = 0; i < WIDTH; i++)
        {
            if (i-k-1 >= 0 && shift != 0)
                pn[i-k-1] |= (a.pn[i] << (32-shift));
            if (i-k >= 0)
                pn[i-k] |= (a.pn[i] >> shift);
        }
        return *this;
    }

    base_uint& operator+=(const base_uint& b)
    {
        uint64 carry = 0;
        for (int i = 0; i < WIDTH; i++)
        {
            uint64 n = carry + pn[i] + b.pn[i];
            pn[i] = n & 0xffffffff;
            carry = n >> 32;
        }
        return *this;
    }

    base_uint& operator-=(const base_uint& b)
    {
        *this += -b;
        return *this;
    }

    base_uint& operator+=(uint64 b64)
    {
        base_uint b;
        b = b64;
        *this += b;
        return *this;
    }

    base_uint& operator-=(uint64 b64)
    {
        base_uint b;
        b = b64;
        *this += -b;
        return *this;
    }


    base_uint& operator++()
    {
        // prefix operator
        int i = 0;
        while (++pn[i] == 0 && i < WIDTH-1)
            i++;
        return *this;
    }

    const base_uint operator++(int)
    {
        // postfix operator
        const base_uint ret = *this;
        ++(*this);
        return ret;
    }

    base_uint& operator--()
    {
        // prefix operator
        int i = 0;
        while (--pn[i] == -1 && i < WIDTH-1)
            i++;
        return *this;
    }

    const base_uint operator--(int)
    {
        // postfix operator
        const base_uint ret = *this;
        --(*this);
        return ret;
    }


    friend inline bool operator<(const base_uint& a, const base_uint& b)
    {
        for (int i = base_uint::WIDTH-1; i >= 0; i--)
        {
            if (a.pn[i] < b.pn[i])
                return true;
            else if (a.pn[i] > b.pn[i])
                return false;
        }
        return false;
    }

    friend inline bool operator<=(const base_uint& a, const base_uint& b)
    {
        for (int i = base_uint::WIDTH-1; i >= 0; i--)
        {
            if (a.pn[i] < b.pn[i])
                return true;
            else if (a.pn[i] > b.pn[i])
                return false;
        }
        return true;
    }

    friend inline bool operator>(const base_uint& a, const base_uint& b)
    {
        for (int i = base_uint::WIDTH-1; i >= 0; i--)
        {
            if (a.pn[i] > b.pn[i])
                return true;
            else if (a.pn[i] < b.pn[i])
                return false;
        }
        return false;
    }

    friend inline bool operator>=(const base_uint& a, const base_uint& b)
    {
        for (int i = base_uint::WIDTH-1; i >= 0; i--)
        {
            if (a.pn[i] > b.pn[i])
                return true;
            else if (a.pn[i] < b.pn[i])
                return false;
        }
        return true;
    }

    friend inline bool operator==(const base_uint& a, const base_uint& b)
    {
        for (int i = 0; i < base_uint::WIDTH; i++)
            if (a.pn[i] != b.pn[i])
                return false;
        return true;
    }

    friend inline bool operator==(const base_uint& a, uint64 b)
    {
        if (a.pn[0] != (unsigned int)b)
            return false;
        if (a.pn[1] != (unsigned int)(b >> 32))
            return false;
        for (int i = 2; i < base_uint::WIDTH; i++)
            if (a.pn[i] != 0)
                return false;
        return true;
    }

    friend inline bool operator!=(const base_uint& a, const base_uint& b)
    {
        return (!(a == b));
    }

    friend inline bool operator!=(const base_uint& a, uint64 b)
    {
        return (!(a == b));
    }



    std::string GetHex() const
    {
        char psz[sizeof(pn)*2 + 1];
        for (unsigned int i = 0; i < sizeof(pn); i++)
            sprintf(psz + i*2, "%02x", ((unsigned char*)pn)[sizeof(pn) - i - 1]);
        return std::string(psz, psz + sizeof(pn)*2);
    }

    void SetHex(const char* psz)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = 0;

        while (isspace(*psz))
            psz++;

        if (psz[0] == '0' && tolower(psz[1]) == 'x')
            psz += 2;


        static unsigned char phexdigit[256] = { 0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0, 0,1,2,3,4,5,6,7,8,9,0,0,0,0,0,0, 0,0xa,0xb,0xc,0xd,0xe,0xf,0,0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0, 0,0xa,0xb,0xc,0xd,0xe,0xf,0,0,0,0,0,0,0,0,0 };
        const char* pbegin = psz;
        while (phexdigit[(unsigned char)*psz] || *psz == '0')
            psz++;
        psz--;
        unsigned char* p1 = (unsigned char*)pn;
        unsigned char* pend = p1 + WIDTH * 4;
        while (psz >= pbegin && p1 < pend)
        {
            *p1 = phexdigit[(unsigned char)*psz--];
            if (psz >= pbegin)
            {
                *p1 |= (phexdigit[(unsigned char)*psz--] << 4);
                p1++;
            }
        }
    }

    void SetHex(const std::string& str)
    {
        SetHex(str.c_str());
    }

    /** Converts the corresponding unsigned integer into bytes.
        Used for serializing in Miner LLP **/
    const std::vector<unsigned char> GetBytes()
    {
        std::vector<unsigned char> DATA;

        for(int index = 0; index < WIDTH; index++)
        {
            std::vector<unsigned char> BYTES(4, 0);
            BYTES[0] = (pn[index] >> 24);
            BYTES[1] = (pn[index] >> 16);
            BYTES[2] = (pn[index] >> 8);
            BYTES[3] =  pn[index];

            DATA.insert(DATA.end(), BYTES.begin(), BYTES.end());
        }

        return DATA;
    }

    /** Creates an unsigned integer from bytes.
        Used for de-serializing in Miner LLP **/
    void SetBytes(const std::vector<unsigned char> DATA)
    {
        for(int index = 0; index < WIDTH; index++)
        {
            std::vector<unsigned char> BYTES(DATA.begin() + (index * 4), DATA.begin() + (index * 4) + 4);
            pn[index] = (BYTES[0] << 24) + (BYTES[1] << 16) + (BYTES[2] << 8) + (BYTES[3] );
        }
    }

    std::string ToString() const
    {
        return (GetHex());
    }

    unsigned char* begin()
    {
        return (unsigned char*)&pn[0];
    }

    unsigned char* end()
    {
        return (unsigned char*)&pn[WIDTH];
    }

    unsigned int size()
    {
        return sizeof(pn);
    }

    uint64 Get64(int n=0) const
    {
        return pn[2*n] | (uint64)pn[2*n+1] << 32;
    }

//    unsigned int GetSerializeSize(int nType=0, int nVersion=PROTOCOL_VERSION) const
    unsigned int GetSerializeSize(int nType, int nVersion) const
    {
        return sizeof(pn);
    }

    template<typename Stream>
//    void Serialize(Stream& s, int nType=0, int nVersion=PROTOCOL_VERSION) const
    void Serialize(Stream& s, int nType, int nVersion) const
    {
        s.write((char*)pn, sizeof(pn));
    }

    template<typename Stream>
//    void Unserialize(Stream& s, int nType=0, int nVersion=PROTOCOL_VERSION)
    void Unserialize(Stream& s, int nType, int nVersion)
    {
        s.read((char*)pn, sizeof(pn));
    }


    friend class uint256;
    friend class uint512;
    friend class uint576;
    friend class uint1024;
};

typedef base_uint<256> base_uint256;
typedef base_uint<512> base_uint512;
typedef base_uint<576> base_uint576;
typedef base_uint<1024> base_uint1024;


/** 256-bit unsigned integer */
class uint256 : public base_uint256
{
public:
    typedef base_uint256 basetype;

    uint256()
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = 0;
    }

    uint256(const basetype& b)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = b.pn[i];
    }

    uint256& operator=(const basetype& b)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = b.pn[i];
        return *this;
    }

    uint256(uint64 b)
    {
        pn[0] = (unsigned int)b;
        pn[1] = (unsigned int)(b >> 32);
        for (int i = 2; i < WIDTH; i++)
            pn[i] = 0;
    }

    uint256& operator=(uint64 b)
    {
        pn[0] = (unsigned int)b;
        pn[1] = (unsigned int)(b >> 32);
        for (int i = 2; i < WIDTH; i++)
            pn[i] = 0;
        return *this;
    }

    explicit uint256(const std::string& str)
    {
        SetHex(str);
    }

    explicit uint256(const std::vector<unsigned char>& vch)
    {
        if (vch.size() == sizeof(pn))
            memcpy(pn, &vch[0], sizeof(pn));
        else
            *this = 0;
    }
};

inline bool operator==(const uint256& a, uint64 b)                           { return (base_uint256)a == b; }
inline bool operator!=(const uint256& a, uint64 b)                           { return (base_uint256)a != b; }
inline const uint256 operator<<(const base_uint256& a, unsigned int shift)   { return uint256(a) <<= shift; }
inline const uint256 operator>>(const base_uint256& a, unsigned int shift)   { return uint256(a) >>= shift; }
inline const uint256 operator<<(const uint256& a, unsigned int shift)        { return uint256(a) <<= shift; }
inline const uint256 operator>>(const uint256& a, unsigned int shift)        { return uint256(a) >>= shift; }

inline const uint256 operator^(const base_uint256& a, const base_uint256& b) { return uint256(a) ^= b; }
inline const uint256 operator&(const base_uint256& a, const base_uint256& b) { return uint256(a) &= b; }
inline const uint256 operator|(const base_uint256& a, const base_uint256& b) { return uint256(a) |= b; }
inline const uint256 operator+(const base_uint256& a, const base_uint256& b) { return uint256(a) += b; }
inline const uint256 operator-(const base_uint256& a, const base_uint256& b) { return uint256(a) -= b; }

inline bool operator<(const base_uint256& a, const uint256& b)          { return (base_uint256)a <  (base_uint256)b; }
inline bool operator<=(const base_uint256& a, const uint256& b)         { return (base_uint256)a <= (base_uint256)b; }
inline bool operator>(const base_uint256& a, const uint256& b)          { return (base_uint256)a >  (base_uint256)b; }
inline bool operator>=(const base_uint256& a, const uint256& b)         { return (base_uint256)a >= (base_uint256)b; }
inline bool operator==(const base_uint256& a, const uint256& b)         { return (base_uint256)a == (base_uint256)b; }
inline bool operator!=(const base_uint256& a, const uint256& b)         { return (base_uint256)a != (base_uint256)b; }
inline const uint256 operator^(const base_uint256& a, const uint256& b) { return (base_uint256)a ^  (base_uint256)b; }
inline const uint256 operator&(const base_uint256& a, const uint256& b) { return (base_uint256)a &  (base_uint256)b; }
inline const uint256 operator|(const base_uint256& a, const uint256& b) { return (base_uint256)a |  (base_uint256)b; }
inline const uint256 operator+(const base_uint256& a, const uint256& b) { return (base_uint256)a +  (base_uint256)b; }
inline const uint256 operator-(const base_uint256& a, const uint256& b) { return (base_uint256)a -  (base_uint256)b; }

inline bool operator<(const uint256& a, const base_uint256& b)          { return (base_uint256)a <  (base_uint256)b; }
inline bool operator<=(const uint256& a, const base_uint256& b)         { return (base_uint256)a <= (base_uint256)b; }
inline bool operator>(const uint256& a, const base_uint256& b)          { return (base_uint256)a >  (base_uint256)b; }
inline bool operator>=(const uint256& a, const base_uint256& b)         { return (base_uint256)a >= (base_uint256)b; }
inline bool operator==(const uint256& a, const base_uint256& b)         { return (base_uint256)a == (base_uint256)b; }
inline bool operator!=(const uint256& a, const base_uint256& b)         { return (base_uint256)a != (base_uint256)b; }
inline const uint256 operator^(const uint256& a, const base_uint256& b) { return (base_uint256)a ^  (base_uint256)b; }
inline const uint256 operator&(const uint256& a, const base_uint256& b) { return (base_uint256)a &  (base_uint256)b; }
inline const uint256 operator|(const uint256& a, const base_uint256& b) { return (base_uint256)a |  (base_uint256)b; }
inline const uint256 operator+(const uint256& a, const base_uint256& b) { return (base_uint256)a +  (base_uint256)b; }
inline const uint256 operator-(const uint256& a, const base_uint256& b) { return (base_uint256)a -  (base_uint256)b; }

inline bool operator<(const uint256& a, const uint256& b)               { return (base_uint256)a <  (base_uint256)b; }
inline bool operator<=(const uint256& a, const uint256& b)              { return (base_uint256)a <= (base_uint256)b; }
inline bool operator>(const uint256& a, const uint256& b)               { return (base_uint256)a >  (base_uint256)b; }
inline bool operator>=(const uint256& a, const uint256& b)              { return (base_uint256)a >= (base_uint256)b; }
inline bool operator==(const uint256& a, const uint256& b)              { return (base_uint256)a == (base_uint256)b; }
inline bool operator!=(const uint256& a, const uint256& b)              { return (base_uint256)a != (base_uint256)b; }
inline const uint256 operator^(const uint256& a, const uint256& b)      { return (base_uint256)a ^  (base_uint256)b; }
inline const uint256 operator&(const uint256& a, const uint256& b)      { return (base_uint256)a &  (base_uint256)b; }
inline const uint256 operator|(const uint256& a, const uint256& b)      { return (base_uint256)a |  (base_uint256)b; }
inline const uint256 operator+(const uint256& a, const uint256& b)      { return (base_uint256)a +  (base_uint256)b; }
inline const uint256 operator-(const uint256& a, const uint256& b)      { return (base_uint256)a -  (base_uint256)b; }


/** 512-bit unsigned integer */
class uint512 : public base_uint512
{
public:
    typedef base_uint512 basetype;

    uint512()
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = 0;
    }

    uint512(const basetype& b)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = b.pn[i];
    }

    uint512& operator=(const basetype& b)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = b.pn[i];
        return *this;
    }

    uint512(uint64 b)
    {
        pn[0] = (unsigned int)b;
        pn[1] = (unsigned int)(b >> 32);
        for (int i = 2; i < WIDTH; i++)
            pn[i] = 0;
    }

    uint512& operator=(uint64 b)
    {
        pn[0] = (unsigned int)b;
        pn[1] = (unsigned int)(b >> 32);
        for (int i = 2; i < WIDTH; i++)
            pn[i] = 0;
        return *this;
    }

    explicit uint512(const std::vector<unsigned char> vch)
    {
        SetBytes(vch);
    }

    explicit uint512(const std::string& str)
    {
        SetHex(str);
    }

    /*
    explicit uint512(const std::vector<unsigned char>& vch)
    {
        if (vch.size() == sizeof(pn))
            memcpy(pn, &vch[0], sizeof(pn));
        else
            *this = 0;
    }
    */
};

inline bool operator==(const uint512& a, uint64 b)                           { return (base_uint512)a == b; }
inline bool operator!=(const uint512& a, uint64 b)                           { return (base_uint512)a != b; }
inline const uint512 operator<<(const base_uint512& a, unsigned int shift)   { return uint512(a) <<= shift; }
inline const uint512 operator>>(const base_uint512& a, unsigned int shift)   { return uint512(a) >>= shift; }
inline const uint512 operator<<(const uint512& a, unsigned int shift)        { return uint512(a) <<= shift; }
inline const uint512 operator>>(const uint512& a, unsigned int shift)        { return uint512(a) >>= shift; }

inline const uint512 operator^(const base_uint512& a, const base_uint512& b) { return uint512(a) ^= b; }
inline const uint512 operator&(const base_uint512& a, const base_uint512& b) { return uint512(a) &= b; }
inline const uint512 operator|(const base_uint512& a, const base_uint512& b) { return uint512(a) |= b; }
inline const uint512 operator+(const base_uint512& a, const base_uint512& b) { return uint512(a) += b; }
inline const uint512 operator-(const base_uint512& a, const base_uint512& b) { return uint512(a) -= b; }

inline bool operator<(const base_uint512& a, const uint512& b)          { return (base_uint512)a <  (base_uint512)b; }
inline bool operator<=(const base_uint512& a, const uint512& b)         { return (base_uint512)a <= (base_uint512)b; }
inline bool operator>(const base_uint512& a, const uint512& b)          { return (base_uint512)a >  (base_uint512)b; }
inline bool operator>=(const base_uint512& a, const uint512& b)         { return (base_uint512)a >= (base_uint512)b; }
inline bool operator==(const base_uint512& a, const uint512& b)         { return (base_uint512)a == (base_uint512)b; }
inline bool operator!=(const base_uint512& a, const uint512& b)         { return (base_uint512)a != (base_uint512)b; }
inline const uint512 operator^(const base_uint512& a, const uint512& b) { return (base_uint512)a ^  (base_uint512)b; }
inline const uint512 operator&(const base_uint512& a, const uint512& b) { return (base_uint512)a &  (base_uint512)b; }
inline const uint512 operator|(const base_uint512& a, const uint512& b) { return (base_uint512)a |  (base_uint512)b; }
inline const uint512 operator+(const base_uint512& a, const uint512& b) { return (base_uint512)a +  (base_uint512)b; }
inline const uint512 operator-(const base_uint512& a, const uint512& b) { return (base_uint512)a -  (base_uint512)b; }

inline bool operator<(const uint512& a, const base_uint512& b)          { return (base_uint512)a <  (base_uint512)b; }
inline bool operator<=(const uint512& a, const base_uint512& b)         { return (base_uint512)a <= (base_uint512)b; }
inline bool operator>(const uint512& a, const base_uint512& b)          { return (base_uint512)a >  (base_uint512)b; }
inline bool operator>=(const uint512& a, const base_uint512& b)         { return (base_uint512)a >= (base_uint512)b; }
inline bool operator==(const uint512& a, const base_uint512& b)         { return (base_uint512)a == (base_uint512)b; }
inline bool operator!=(const uint512& a, const base_uint512& b)         { return (base_uint512)a != (base_uint512)b; }
inline const uint512 operator^(const uint512& a, const base_uint512& b) { return (base_uint512)a ^  (base_uint512)b; }
inline const uint512 operator&(const uint512& a, const base_uint512& b) { return (base_uint512)a &  (base_uint512)b; }
inline const uint512 operator|(const uint512& a, const base_uint512& b) { return (base_uint512)a |  (base_uint512)b; }
inline const uint512 operator+(const uint512& a, const base_uint512& b) { return (base_uint512)a +  (base_uint512)b; }
inline const uint512 operator-(const uint512& a, const base_uint512& b) { return (base_uint512)a -  (base_uint512)b; }

inline bool operator<(const uint512& a, const uint512& b)               { return (base_uint512)a <  (base_uint512)b; }
inline bool operator<=(const uint512& a, const uint512& b)              { return (base_uint512)a <= (base_uint512)b; }
inline bool operator>(const uint512& a, const uint512& b)               { return (base_uint512)a >  (base_uint512)b; }
inline bool operator>=(const uint512& a, const uint512& b)              { return (base_uint512)a >= (base_uint512)b; }
inline bool operator==(const uint512& a, const uint512& b)              { return (base_uint512)a == (base_uint512)b; }
inline bool operator!=(const uint512& a, const uint512& b)              { return (base_uint512)a != (base_uint512)b; }
inline const uint512 operator^(const uint512& a, const uint512& b)      { return (base_uint512)a ^  (base_uint512)b; }
inline const uint512 operator&(const uint512& a, const uint512& b)      { return (base_uint512)a &  (base_uint512)b; }
inline const uint512 operator|(const uint512& a, const uint512& b)      { return (base_uint512)a |  (base_uint512)b; }
inline const uint512 operator+(const uint512& a, const uint512& b)      { return (base_uint512)a +  (base_uint512)b; }
inline const uint512 operator-(const uint512& a, const uint512& b)      { return (base_uint512)a -  (base_uint512)b; }

/** 576-bit unsigned integer */
class uint576 : public base_uint576
{
public:
    typedef base_uint576 basetype;

    uint576()
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = 0;
    }

    uint576(const basetype& b)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = b.pn[i];
    }

    uint576& operator=(const basetype& b)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = b.pn[i];
        return *this;
    }

    uint576(uint64 b)
    {
        pn[0] = (unsigned int)b;
        pn[1] = (unsigned int)(b >> 32);
        for (int i = 2; i < WIDTH; i++)
            pn[i] = 0;
    }

    uint576& operator=(uint64 b)
    {
        pn[0] = (unsigned int)b;
        pn[1] = (unsigned int)(b >> 32);
        for (int i = 2; i < WIDTH; i++)
            pn[i] = 0;
        return *this;
    }

    explicit uint576(const std::string& str)
    {
        SetHex(str);
    }

    explicit uint576(const std::vector<unsigned char>& vch)
    {
        if (vch.size() == sizeof(pn))
            memcpy(pn, &vch[0], sizeof(pn));
        else
            *this = 0;
    }
};

inline bool operator==(const uint576& a, uint64 b)                           { return (base_uint576)a == b; }
inline bool operator!=(const uint576& a, uint64 b)                           { return (base_uint576)a != b; }
inline const uint576 operator<<(const base_uint576& a, unsigned int shift)   { return uint576(a) <<= shift; }
inline const uint576 operator>>(const base_uint576& a, unsigned int shift)   { return uint576(a) >>= shift; }
inline const uint576 operator<<(const uint576& a, unsigned int shift)        { return uint576(a) <<= shift; }
inline const uint576 operator>>(const uint576& a, unsigned int shift)        { return uint576(a) >>= shift; }

inline const uint576 operator^(const base_uint576& a, const base_uint576& b) { return uint576(a) ^= b; }
inline const uint576 operator&(const base_uint576& a, const base_uint576& b) { return uint576(a) &= b; }
inline const uint576 operator|(const base_uint576& a, const base_uint576& b) { return uint576(a) |= b; }
inline const uint576 operator+(const base_uint576& a, const base_uint576& b) { return uint576(a) += b; }
inline const uint576 operator-(const base_uint576& a, const base_uint576& b) { return uint576(a) -= b; }

inline bool operator<(const base_uint576& a, const uint576& b)          { return (base_uint576)a <  (base_uint576)b; }
inline bool operator<=(const base_uint576& a, const uint576& b)         { return (base_uint576)a <= (base_uint576)b; }
inline bool operator>(const base_uint576& a, const uint576& b)          { return (base_uint576)a >  (base_uint576)b; }
inline bool operator>=(const base_uint576& a, const uint576& b)         { return (base_uint576)a >= (base_uint576)b; }
inline bool operator==(const base_uint576& a, const uint576& b)         { return (base_uint576)a == (base_uint576)b; }
inline bool operator!=(const base_uint576& a, const uint576& b)         { return (base_uint576)a != (base_uint576)b; }
inline const uint576 operator^(const base_uint576& a, const uint576& b) { return (base_uint576)a ^  (base_uint576)b; }
inline const uint576 operator&(const base_uint576& a, const uint576& b) { return (base_uint576)a &  (base_uint576)b; }
inline const uint576 operator|(const base_uint576& a, const uint576& b) { return (base_uint576)a |  (base_uint576)b; }
inline const uint576 operator+(const base_uint576& a, const uint576& b) { return (base_uint576)a +  (base_uint576)b; }
inline const uint576 operator-(const base_uint576& a, const uint576& b) { return (base_uint576)a -  (base_uint576)b; }

inline bool operator<(const uint576& a, const base_uint576& b)          { return (base_uint576)a <  (base_uint576)b; }
inline bool operator<=(const uint576& a, const base_uint576& b)         { return (base_uint576)a <= (base_uint576)b; }
inline bool operator>(const uint576& a, const base_uint576& b)          { return (base_uint576)a >  (base_uint576)b; }
inline bool operator>=(const uint576& a, const base_uint576& b)         { return (base_uint576)a >= (base_uint576)b; }
inline bool operator==(const uint576& a, const base_uint576& b)         { return (base_uint576)a == (base_uint576)b; }
inline bool operator!=(const uint576& a, const base_uint576& b)         { return (base_uint576)a != (base_uint576)b; }
inline const uint576 operator^(const uint576& a, const base_uint576& b) { return (base_uint576)a ^  (base_uint576)b; }
inline const uint576 operator&(const uint576& a, const base_uint576& b) { return (base_uint576)a &  (base_uint576)b; }
inline const uint576 operator|(const uint576& a, const base_uint576& b) { return (base_uint576)a |  (base_uint576)b; }
inline const uint576 operator+(const uint576& a, const base_uint576& b) { return (base_uint576)a +  (base_uint576)b; }
inline const uint576 operator-(const uint576& a, const base_uint576& b) { return (base_uint576)a -  (base_uint576)b; }

inline bool operator<(const uint576& a, const uint576& b)               { return (base_uint576)a <  (base_uint576)b; }
inline bool operator<=(const uint576& a, const uint576& b)              { return (base_uint576)a <= (base_uint576)b; }
inline bool operator>(const uint576& a, const uint576& b)               { return (base_uint576)a >  (base_uint576)b; }
inline bool operator>=(const uint576& a, const uint576& b)              { return (base_uint576)a >= (base_uint576)b; }
inline bool operator==(const uint576& a, const uint576& b)              { return (base_uint576)a == (base_uint576)b; }
inline bool operator!=(const uint576& a, const uint576& b)              { return (base_uint576)a != (base_uint576)b; }
inline const uint576 operator^(const uint576& a, const uint576& b)      { return (base_uint576)a ^  (base_uint576)b; }
inline const uint576 operator&(const uint576& a, const uint576& b)      { return (base_uint576)a &  (base_uint576)b; }
inline const uint576 operator|(const uint576& a, const uint576& b)      { return (base_uint576)a |  (base_uint576)b; }
inline const uint576 operator+(const uint576& a, const uint576& b)      { return (base_uint576)a +  (base_uint576)b; }
inline const uint576 operator-(const uint576& a, const uint576& b)      { return (base_uint576)a -  (base_uint576)b; }



/** 1024-bit unsigned integer */
class uint1024 : public base_uint1024
{
public:
    typedef base_uint1024 basetype;

    uint1024()
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = 0;
    }

    uint1024(const basetype& b)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = b.pn[i];
    }

    uint1024& operator=(const basetype& b)
    {
        for (int i = 0; i < WIDTH; i++)
            pn[i] = b.pn[i];
        return *this;
    }

    uint1024(uint64 b)
    {
        pn[0] = (unsigned int)b;
        pn[1] = (unsigned int)(b >> 32);
        for (int i = 2; i < WIDTH; i++)
            pn[i] = 0;
    }

    uint1024& operator=(uint64 b)
    {
        pn[0] = (unsigned int)b;
        pn[1] = (unsigned int)(b >> 32);
        for (int i = 2; i < WIDTH; i++)
            pn[i] = 0;
        return *this;
    }

    uint1024(uint256 b)
    {
        for (int i = 0; i < WIDTH; i++)
            if(i < b.WIDTH)
                pn[i] = b.pn[i];
            else
                pn[i] = 0;
    }

    uint1024& operator=(uint256 b)
    {
        for (int i = 0; i < WIDTH; i++)
            if(i < b.WIDTH)
                pn[i] = b.pn[i];
            else
                pn[i] = 0;

        return *this;
    }

    uint1024(uint512 b)
    {
        for (int i = 0; i < WIDTH; i++)
            if(i < b.WIDTH)
                pn[i] = b.pn[i];
            else
                pn[i] = 0;
    }

    uint1024& operator=(uint512 b)
    {
        for (int i = 0; i < WIDTH; i++)
            if(i < b.WIDTH)
                pn[i] = b.pn[i];
            else
                pn[i] = 0;

        return *this;
    }

    unsigned int high_bits(unsigned int mask)
    {
      return pn[WIDTH-1] & mask;
    }

    /** This method should only be used to retrieve an uint256 when stored inside an uint1024.
        This is necessary for for ambiguous function declaration. */
    uint256 getuint256() const
    {
        uint256 b;
        for (int i = 0; i < b.WIDTH; i++)
            b.pn[i] = pn[i];

        return b;
    }

    /** This method should only be used to retrieve an uint512 when stored inside an uint1024.
        This is necessary for the inventory system to function with both a 1024 bit block
        and 512 bit transaction. */
    uint512 getuint512() const
    {
        uint512 b;
        for (int i = 0; i < b.WIDTH; i++)
            b.pn[i] = pn[i];

        return b;
    }

    explicit uint1024(const std::string& str)
    {
        SetHex(str);
    }

    explicit uint1024(const std::vector<unsigned char>& vch)
    {
        if (vch.size() == sizeof(pn))
            memcpy(pn, &vch[0], sizeof(pn));
        else
            *this = 0;
    }
};

inline bool operator==(const uint1024& a, uint64 b)                           { return (base_uint1024)a == b; }
inline bool operator!=(const uint1024& a, uint64 b)                           { return (base_uint1024)a != b; }
inline const uint1024 operator<<(const base_uint1024& a, unsigned int shift)   { return uint1024(a) <<= shift; }
inline const uint1024 operator>>(const base_uint1024& a, unsigned int shift)   { return uint1024(a) >>= shift; }
inline const uint1024 operator<<(const uint1024& a, unsigned int shift)        { return uint1024(a) <<= shift; }
inline const uint1024 operator>>(const uint1024& a, unsigned int shift)        { return uint1024(a) >>= shift; }

inline const uint1024 operator^(const base_uint1024& a, const base_uint1024& b) { return uint1024(a) ^= b; }
inline const uint1024 operator&(const base_uint1024& a, const base_uint1024& b) { return uint1024(a) &= b; }
inline const uint1024 operator|(const base_uint1024& a, const base_uint1024& b) { return uint1024(a) |= b; }
inline const uint1024 operator+(const base_uint1024& a, const base_uint1024& b) { return uint1024(a) += b; }
inline const uint1024 operator-(const base_uint1024& a, const base_uint1024& b) { return uint1024(a) -= b; }

inline bool operator<(const base_uint1024& a, const uint1024& b)          { return (base_uint1024)a <  (base_uint1024)b; }
inline bool operator<=(const base_uint1024& a, const uint1024& b)         { return (base_uint1024)a <= (base_uint1024)b; }
inline bool operator>(const base_uint1024& a, const uint1024& b)          { return (base_uint1024)a >  (base_uint1024)b; }
inline bool operator>=(const base_uint1024& a, const uint1024& b)         { return (base_uint1024)a >= (base_uint1024)b; }
inline bool operator==(const base_uint1024& a, const uint1024& b)         { return (base_uint1024)a == (base_uint1024)b; }
inline bool operator!=(const base_uint1024& a, const uint1024& b)         { return (base_uint1024)a != (base_uint1024)b; }
inline const uint1024 operator^(const base_uint1024& a, const uint1024& b) { return (base_uint1024)a ^  (base_uint1024)b; }
inline const uint1024 operator&(const base_uint1024& a, const uint1024& b) { return (base_uint1024)a &  (base_uint1024)b; }
inline const uint1024 operator|(const base_uint1024& a, const uint1024& b) { return (base_uint1024)a |  (base_uint1024)b; }
inline const uint1024 operator+(const base_uint1024& a, const uint1024& b) { return (base_uint1024)a +  (base_uint1024)b; }
inline const uint1024 operator-(const base_uint1024& a, const uint1024& b) { return (base_uint1024)a -  (base_uint1024)b; }

inline bool operator<(const uint1024& a, const base_uint1024& b)          { return (base_uint1024)a <  (base_uint1024)b; }
inline bool operator<=(const uint1024& a, const base_uint1024& b)         { return (base_uint1024)a <= (base_uint1024)b; }
inline bool operator>(const uint1024& a, const base_uint1024& b)          { return (base_uint1024)a >  (base_uint1024)b; }
inline bool operator>=(const uint1024& a, const base_uint1024& b)         { return (base_uint1024)a >= (base_uint1024)b; }
inline bool operator==(const uint1024& a, const base_uint1024& b)         { return (base_uint1024)a == (base_uint1024)b; }
inline bool operator!=(const uint1024& a, const base_uint1024& b)         { return (base_uint1024)a != (base_uint1024)b; }
inline const uint1024 operator^(const uint1024& a, const base_uint1024& b) { return (base_uint1024)a ^  (base_uint1024)b; }
inline const uint1024 operator&(const uint1024& a, const base_uint1024& b) { return (base_uint1024)a &  (base_uint1024)b; }
inline const uint1024 operator|(const uint1024& a, const base_uint1024& b) { return (base_uint1024)a |  (base_uint1024)b; }
inline const uint1024 operator+(const uint1024& a, const base_uint1024& b) { return (base_uint1024)a +  (base_uint1024)b; }
inline const uint1024 operator-(const uint1024& a, const base_uint1024& b) { return (base_uint1024)a -  (base_uint1024)b; }

inline bool operator<(const uint1024& a, const uint1024& b)               { return (base_uint1024)a <  (base_uint1024)b; }
inline bool operator<=(const uint1024& a, const uint1024& b)              { return (base_uint1024)a <= (base_uint1024)b; }
inline bool operator>(const uint1024& a, const uint1024& b)               { return (base_uint1024)a >  (base_uint1024)b; }
inline bool operator>=(const uint1024& a, const uint1024& b)              { return (base_uint1024)a >= (base_uint1024)b; }
inline bool operator==(const uint1024& a, const uint1024& b)              { return (base_uint1024)a == (base_uint1024)b; }
inline bool operator!=(const uint1024& a, const uint1024& b)              { return (base_uint1024)a != (base_uint1024)b; }
inline const uint1024 operator^(const uint1024& a, const uint1024& b)      { return (base_uint1024)a ^  (base_uint1024)b; }
inline const uint1024 operator&(const uint1024& a, const uint1024& b)      { return (base_uint1024)a &  (base_uint1024)b; }
inline const uint1024 operator|(const uint1024& a, const uint1024& b)      { return (base_uint1024)a |  (base_uint1024)b; }
inline const uint1024 operator+(const uint1024& a, const uint1024& b)      { return (base_uint1024)a +  (base_uint1024)b; }
inline const uint1024 operator-(const uint1024& a, const uint1024& b)      { return (base_uint1024)a -  (base_uint1024)b; }



#endif
