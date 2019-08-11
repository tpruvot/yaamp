/*__________________________________________________________________________________________

			(c) Hash(BEGIN(Satoshi[2010]), END(Sunny[2012])) == Videlicet[2014] ++

			(c) Copyright The Nexus Developers 2014 - 2019

			Distributed under the MIT software license, see the accompanying
			file COPYING or http://www.opensource.org/licenses/mit-license.php.

			"ad vocem populi" - To the Voice of the People

____________________________________________________________________________________________*/

#pragma once
//#ifndef NEXUS_LLC_HASH_SK_H
//#define NEXUS_LLC_HASH_SK_H

#include <uint1024.h>
#include <skein-nxs.h>
#include <Keccak-nxs.h>

/** Namespace LLC (Lower Level Crypto) **/
namespace LLC
{

	static uint8_t pblank[1];


    /** SK32
     *
     * 32-bit hashing template for Checksums.
     *
     **/
	inline uint32_t SK32(const std::vector<uint8_t> vch)
	{
		uint32_t skein;
		Skein_256_Ctxt_t ctx;
		Skein_256_Init(&ctx, 32);
		Skein_256_Update(&ctx, (uint8_t *)&vch[0], vch.size());
		Skein_256_Final(&ctx, (uint8_t *)&skein);

		uint32_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize(&ctx_keccak, 1344, 256, 32, 0x06);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 32);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


	/** SK32
     *
     *  32-bit hashing template for Checksums.
     *
     **/
	template<typename T1>
	inline uint32_t SK32(const T1 pbegin, const T1 pend)
	{
		uint32_t skein;
		Skein_256_Ctxt_t ctx;
		Skein_256_Init  (&ctx, 32);
		Skein_256_Update(&ctx, (pbegin == pend ? pblank : (uint8_t*)&pbegin[0]), (pend - pbegin) * sizeof(pbegin[0]));
		Skein_256_Final (&ctx, (uint8_t *)&skein);

		uint32_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize(&ctx_keccak, 1344, 256, 32, 0x06);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 32);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


	/** SK64
     *
     *  64-bit hashing template for Checksums.
     *
     **/
	template<typename T1>
	inline uint64_t SK64(const T1 pbegin, const T1 pend)
	{
		uint64_t skein;
		Skein_256_Ctxt_t ctx;
		Skein_256_Init  (&ctx, 64);
		Skein_256_Update(&ctx, (pbegin == pend ? pblank : (uint8_t*)&pbegin[0]), (pend - pbegin) * sizeof(pbegin[0]));
		Skein_256_Final (&ctx, (uint8_t *)&skein);

		uint64_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize(&ctx_keccak, 1344, 256, 64, 0x06);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 64);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


	/** SK64
     *
     *  64-bit hashing template for Address Generation.
     *
     **/
	inline uint64_t SK64(const std::vector<uint8_t>& vch)
	{
		uint64_t skein;
		Skein_256_Ctxt_t ctx;
		Skein_256_Init(&ctx, 64);
		Skein_256_Update(&ctx, (uint8_t *)&vch[0], vch.size());
		Skein_256_Final(&ctx, (uint8_t *)&skein);

		uint64_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize(&ctx_keccak, 1344, 256, 64, 0x06);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 64);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


	/** SK256
     *
     *  256-bit hashing template for Address Generation .
     *
     **/
	inline uint256_t SK256(const std::vector<uint8_t>& vch)
	{
		uint256_t skein;
		Skein_256_Ctxt_t ctx;
		Skein_256_Init(&ctx, 256);
		Skein_256_Update(&ctx, (uint8_t *)&vch[0], vch.size());
		Skein_256_Final(&ctx, (uint8_t *)&skein);

		uint256_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize_SHA3_256(&ctx_keccak);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 256);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


	/** SK256
     *
     *  256-bit hashing template for Address Generation.
     *
     **/
	template<typename T1>
	inline uint256_t SK256(const T1 pbegin, const T1 pend)
	{
		uint256_t skein;
		Skein_256_Ctxt_t ctx;
		Skein_256_Init  (&ctx, 256);
		Skein_256_Update(&ctx, (pbegin == pend ? pblank : (uint8_t*)&pbegin[0]), (pend - pbegin) * sizeof(pbegin[0]));
		Skein_256_Final (&ctx, (uint8_t *)&skein);

		uint256_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize_SHA3_256(&ctx_keccak);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 256);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


    /** SK512
     *
     *  512-bit hashing template for Next Hash.
     *
     **/
    inline uint512_t SK512(const std::vector<uint8_t>& vch)
	{
		uint512_t skein;
		Skein_512_Ctxt_t ctx;
		Skein_512_Init(&ctx, 512);
		Skein_512_Update(&ctx, (uint8_t *)&vch[0], vch.size());
		Skein_512_Final(&ctx, (uint8_t *)&skein);

		uint512_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize_SHA3_512(&ctx_keccak);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 512);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


	/** SK512
     *
     *  512-bit hashing template for Trust Key Hash.
     *
     **/
	template<typename T1>
	inline uint512_t SK512(const std::vector<uint8_t>& vch, const T1 pbegin, const T1 pend)
	{
		uint512_t skein;
		Skein_512_Ctxt_t ctx;
		Skein_512_Init(&ctx, 512);
		Skein_512_Update(&ctx, (uint8_t *)&vch[0], vch.size());
		Skein_512_Update(&ctx, (pbegin == pend ? pblank : (uint8_t*)&pbegin[0]), (pend - pbegin) * sizeof(pbegin[0]));
		Skein_512_Final(&ctx, (uint8_t *)&skein);

		uint512_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize_SHA3_512(&ctx_keccak);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 512);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


	/** SK512
     *
     *  512-bit hashing template for TX hash.
     *
     **/
	template<typename T1>
	inline uint512_t SK512(const T1 pbegin, const T1 pend)
	{
		uint512_t skein;
		Skein_512_Ctxt_t ctx;
		Skein_512_Init  (&ctx, 512);
		Skein_512_Update(&ctx, (pbegin == pend ? pblank : (uint8_t*)&pbegin[0]), (pend - pbegin) * sizeof(pbegin[0]));
		Skein_512_Final (&ctx, (uint8_t *)&skein);

		uint512_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize_SHA3_512(&ctx_keccak);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 512);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


	/** SK512
     *
     * 512-bit hashing template for TX hash.
     *
     **/
	template<typename T1, typename T2>
	inline uint512_t SK512(const T1 p1begin, const T1 p1end,
						const T2 p2begin, const T2 p2end)
	{
		uint512_t skein;
		Skein_512_Ctxt_t ctx;
		Skein_512_Init  (&ctx, 512);
		Skein_512_Update(&ctx, (p1begin == p1end ? pblank : (uint8_t*)&p1begin[0]), (p1end - p1begin) * sizeof(p1begin[0]));
		Skein_512_Update(&ctx, (p2begin == p2end ? pblank : (uint8_t*)&p2begin[0]), (p2end - p2begin) * sizeof(p2begin[0]));
		Skein_512_Final (&ctx, (uint8_t *)&skein);

		uint512_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize_SHA3_512(&ctx_keccak);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 512);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


	/** SK512
     *
     *  512-bit hashing template for TX hash.
     *
     **/
	template<typename T1, typename T2, typename T3>
	inline uint512_t SK512(const T1 p1begin, const T1 p1end,
						const T2 p2begin, const T2 p2end,
						const T3 p3begin, const T3 p3end)
	{
		uint512_t skein;
		Skein_512_Ctxt_t ctx;
		Skein_512_Init  (&ctx, 512);
		Skein_512_Update(&ctx, (p1begin == p1end ? pblank : (uint8_t*)&p1begin[0]), (p1end - p1begin) * sizeof(p1begin[0]));
		Skein_512_Update(&ctx, (p2begin == p2end ? pblank : (uint8_t*)&p2begin[0]), (p2end - p2begin) * sizeof(p2begin[0]));
		Skein_512_Update(&ctx, (p3begin == p3end ? pblank : (uint8_t*)&p3begin[0]), (p3end - p3begin) * sizeof(p3begin[0]));
		Skein_512_Final (&ctx, (uint8_t *)&skein);

		uint512_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize_SHA3_512(&ctx_keccak);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 512);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


	/** SK576
     *
     * 576-bit hashing template used for Private Keys.
     *
     **/
	template<typename T1>
	inline uint576_t SK576(const T1 pbegin, const T1 pend)
	{
		uint576_t skein;
		Skein1024_Ctxt_t ctx;
		Skein1024_Init(&ctx, 576);
		Skein1024_Update(&ctx, (pbegin == pend ? pblank : (uint8_t*)&pbegin[0]), (pend - pbegin) * sizeof(pbegin[0]));
		Skein1024_Final(&ctx, (uint8_t *)&skein);

		uint576_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize(&ctx_keccak, 1024, 576, 576, 0x06);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 576);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


    /** SK1024
     *
     *  1024-bit hashing template used to build Sig-Chains.
     *
     **/
    inline uint1024_t SK1024(const std::vector<uint8_t>& vch)
	{
		uint1024_t skein;
		Skein1024_Ctxt_t ctx;
		Skein1024_Init(&ctx, 1024);
		Skein1024_Update(&ctx, (uint8_t *)&vch[0], vch.size());
		Skein1024_Final(&ctx, (uint8_t *)&skein);

		uint1024_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize(&ctx_keccak, 576, 1024, 1024, 0x05);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 1024);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}


	/** SK1024
     *
     *  1024-bit hashing template used to build Block Hashes.
     *
     **/
	template<typename T1>
	inline uint1024_t SK1024(const T1 pbegin, const T1 pend)
	{
		uint1024_t skein;
		Skein1024_Ctxt_t ctx;
		Skein1024_Init(&ctx, 1024);
		Skein1024_Update(&ctx, (pbegin == pend ? pblank : (uint8_t*)&pbegin[0]), (pend - pbegin) * sizeof(pbegin[0]));
		Skein1024_Final(&ctx, (uint8_t *)&skein);

		uint1024_t keccak;
		Keccak_HashInstance ctx_keccak;
		Keccak_HashInitialize(&ctx_keccak, 576, 1024, 1024, 0x05);
		Keccak_HashUpdate(&ctx_keccak, (uint8_t *)&skein, 1024);
		Keccak_HashFinal(&ctx_keccak, (uint8_t *)&keccak);

		return keccak;
	}
}

#endif
