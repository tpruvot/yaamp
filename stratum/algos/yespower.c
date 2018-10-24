#include "yespower-opt.c"

static const yespower_params_t yespower_0_5_R8 = {YESPOWER_0_5, 2048, 8, "Client Key", 10};
static const yespower_params_t yespower_0_5_R16 = {YESPOWER_0_5, 4096, 16, "Client Key", 10};
static const yespower_params_t yespower_0_5_R24 = {YESPOWER_0_5, 4096, 24, "Jagaricoin", 10};
static const yespower_params_t yespower_0_5_R32 = {YESPOWER_0_5, 4096, 32, "WaviBanana", 10};
// WARNING DO NOT USE THIS IN yespower_hash YET. For later HF to yespower 1.0
// TODO Find HF solution using unixtime or blockheight(recommended)

/*
Parameter selection for yespower 1.0.
For new uses of yespower, set the requested version to the highest
supported, and set N*r to the highest you can reasonably afford in terms
of proof verification time (which might in turn be determined by desired
share rate per mining pool server), using one of the following options:
1 MiB: N = 1024, r = 8
2 MiB: N = 2048, r = 8    (current bitzeny-yescrypt0.5)
4 MiB: N = 1024, r = 32
8 MiB: N = 2048, r = 32   (recommended settings)
16 MiB: N = 4096, r = 32  (It might increase resistance against future GPUs)
and so on for higher N keeping r=32.
You may also set the personalization string to your liking, but that is
not required (you can set its pointer to NULL and its length to 0).  Its
support is provided mostly for compatibility with existing modifications
of yescrypt 0.5.
*/

// solardiz recommended yespower 1.0 settings of at least N=2048 r=32 (8 MiB)
static const yespower_params_t yespower_1_0 = {YESPOWER_1_0, 2048, 32, NULL, 0};

void yespowerR8_hash(const char *input, char *output, uint32_t len)
{
	if (yespower_tls(input, 80, &yespower_0_5_R8, (yespower_binary_t *)output))
		abort();
}
void yespowerR16_hash(const char *input, char *output, uint32_t len)
{
	if (yespower_tls(input, 80, &yespower_0_5_R16, (yespower_binary_t *)output))
		abort();
}
void yespowerR24_hash(const char *input, char *output, uint32_t len)
{
	if (yespower_tls(input, 80, &yespower_0_5_R24, (yespower_binary_t *)output))
		abort();
}
void yespowerR32_hash(const char *input, char *output, uint32_t len)
{
	if (yespower_tls(input, 80, &yespower_0_5_R32, (yespower_binary_t *)output))
		abort();
}
void yespower_hash(const char *input, char *output, uint32_t len)
{
	if (yespower_tls(input, 80, &yespower_1_0, (yespower_binary_t *)output))
		abort();
}