/*
The Keccak sponge function, designed by Guido Bertoni, Joan Daemen,
Michaël Peeters and Gilles Van Assche. For more information, feedback or
questions, please refer to our website: http://keccak.noekeon.org/

Implementation by the designers,
hereby denoted as "the implementer".

To the extent possible under law, the implementer has waived all copyright
and related or neighboring rights to the source code in this file.
http://creativecommons.org/publicdomain/zero/1.0/
*/

#include <string.h>

#include <./KeccakHash.h>
#include <./KeccakF-1600-interface.h>

/* ---------------------------------------------------------------- */

HashReturn Keccak_HashInitialize(Keccak_HashInstance *instance, uint32_t rate, uint32_t capacity, uint32_t hashbitlen, uint8_t delimitedSuffix)
{
    HashReturn result;

    if (delimitedSuffix == 0)
        return FAIL;
    result = static_cast<HashReturn>(Keccak_SpongeInitialize(&instance->sponge, rate, capacity));
    if (result != SUCCESS)
        return result;
    instance->fixedOutputLength = hashbitlen;
    instance->delimitedSuffix = delimitedSuffix;
    return SUCCESS;
}

/* ---------------------------------------------------------------- */

HashReturn Keccak_HashUpdate(Keccak_HashInstance *instance, const BitSequence *data, DataLength databitlen)
{
    if ((databitlen % 8) == 0)
        return static_cast<HashReturn>(Keccak_SpongeAbsorb(&instance->sponge, data, databitlen/8));
    else {
        HashReturn ret = static_cast<HashReturn>(Keccak_SpongeAbsorb(&instance->sponge, data, databitlen/8));
        if (ret == SUCCESS)
        {
            // The last partial byte is assumed to be aligned on the least significant bits
            uint8_t lastByte = data[databitlen/8];
            // Concatenate the last few bits provided here with those of the suffix
            uint16_t delimitedLastBytes = (uint16_t)((uint16_t)lastByte | ((uint16_t)instance->delimitedSuffix << (databitlen % 8)));
            if ((delimitedLastBytes & 0xFF00) == 0x0000)
            {
                instance->delimitedSuffix = static_cast<uint8_t>(delimitedLastBytes) & 0xFF;
            }
            else
            {
                uint8_t oneByte[1];
                oneByte[0] = static_cast<uint8_t>(delimitedLastBytes) & 0xFF;
                ret = static_cast<HashReturn>(Keccak_SpongeAbsorb(&instance->sponge, oneByte, 1));
                instance->delimitedSuffix = static_cast<uint8_t>(delimitedLastBytes >> 8) & 0xFF;
            }
        }
        return ret;
    }
}

/* ---------------------------------------------------------------- */

HashReturn Keccak_HashFinal(Keccak_HashInstance *instance, BitSequence *hashval)
{
    HashReturn ret = static_cast<HashReturn>(Keccak_SpongeAbsorbLastFewBits(&instance->sponge, instance->delimitedSuffix));
    if (ret == SUCCESS)
        return static_cast<HashReturn>(Keccak_SpongeSqueeze(&instance->sponge, hashval, instance->fixedOutputLength/8));
    else
        return ret;
}

/* ---------------------------------------------------------------- */

HashReturn Keccak_HashSqueeze(Keccak_HashInstance *instance, BitSequence *data, DataLength databitlen)
{
    if ((databitlen % 8) != 0)
        return FAIL;
    return static_cast<HashReturn>(Keccak_SpongeSqueeze(&instance->sponge, data, databitlen/8));
}
