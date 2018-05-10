#ifndef POMELO_H
#define POMELO_H

#ifdef __cplusplus
extern "C" {
#endif

int POMELO(void *out, size_t outlen, const void *in, size_t inlen, const void *salt, size_t saltlen, unsigned int t_cost, unsigned int m_cost);

#ifdef __cplusplus
}
#endif

#endif

