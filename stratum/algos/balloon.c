#include <stdbool.h>
#include <stdint.h>
#include <stddef.h>
#include <string.h>
#include <openssl/aes.h>
#include <openssl/evp.h>
#include "sha256.h"
#include "balloon.h"

#define MIN(a, b) (((a) < (b)) ? (a) : (b))

#ifdef __cplusplus
extern "C"{
#endif

static void balloon_init (struct balloon_options *opts, int64_t s_cost, int32_t t_cost) {
  opts->s_cost = s_cost;
  opts->t_cost = t_cost;
}

void balloon_hash (const char* input, char* output, uint32_t len) {
  balloon ((unsigned char *)input, (unsigned char *)output, len, 128, 4);
}

void balloon (unsigned char *input, unsigned char *output, int32_t len, int64_t s_cost, int32_t t_cost) {
  struct balloon_options opts;
  struct hash_state s;
  balloon_init (&opts, s_cost, t_cost);
  hash_state_init (&s, &opts, input);
  hash_state_fill (&s, input, input, len);
  hash_state_mix (&s, t_cost);
  hash_state_extract (&s, output);
  hash_state_free (&s);
}

int bitstream_init (struct bitstream *b) {
  SHA256_Init(&b->c);
  b->initialized = false;
#if   OPENSSL_VERSION_NUMBER >= 0x10100000L
  b->ctx = EVP_CIPHER_CTX_new();
  EVP_CIPHER_CTX_init(b->ctx);
#else
  EVP_CIPHER_CTX_init (&b->ctx);
#endif
  b->zeros = malloc (BITSTREAM_BUF_SIZE * sizeof (uint8_t));
  memset (b->zeros, 0, BITSTREAM_BUF_SIZE);
}

int bitstream_free (struct bitstream *b) {
  uint8_t out[AES_BLOCK_SIZE];
  int outl;
#if   OPENSSL_VERSION_NUMBER >= 0x10100000L
  EVP_EncryptFinal (b->ctx, out, &outl);
  EVP_CIPHER_CTX_cleanup (b->ctx);
  EVP_CIPHER_CTX_free(b->ctx);
#else
  EVP_EncryptFinal (&b->ctx, out, &outl);
  EVP_CIPHER_CTX_cleanup (&b->ctx);
#endif
  free (b->zeros);
}

int bitstream_seed_add (struct bitstream *b, const void *seed, size_t seedlen) {
  SHA256_Update(&b->c, seed, seedlen);
}

int bitstream_seed_finalize (struct bitstream *b) {
  uint8_t key_bytes[32];
  SHA256_Final (key_bytes, &b->c);
  uint8_t iv[AES_BLOCK_SIZE];
  memset (iv, 0, AES_BLOCK_SIZE);
#if   OPENSSL_VERSION_NUMBER >= 0x10100000L
  EVP_CIPHER_CTX_set_padding (b->ctx, 1);
  EVP_EncryptInit (b->ctx, EVP_aes_128_ctr (), key_bytes, iv);
#else
  EVP_CIPHER_CTX_set_padding (&b->ctx, 1);
  EVP_EncryptInit (&b->ctx, EVP_aes_128_ctr (), key_bytes, iv);
#endif
  b->initialized = true;
}

static int encrypt_partial (struct bitstream *b, void *outp, int to_encrypt) {
  int encl;
#if   OPENSSL_VERSION_NUMBER >= 0x10100000L
  EVP_EncryptUpdate (b->ctx, outp, &encl, b->zeros, to_encrypt);
#else
  EVP_EncryptUpdate (&b->ctx, outp, &encl, b->zeros, to_encrypt);
#endif
}

int bitstream_fill_buffer (struct bitstream *b, void *out, size_t outlen) {
  size_t total = 0;
  while (total < outlen) {
    const int to_encrypt = MIN(outlen - total, BITSTREAM_BUF_SIZE);
    encrypt_partial (b, out + total, to_encrypt);
    total += to_encrypt;
  }
}

int compressb (uint64_t *counter, uint8_t *out, const uint8_t *blocks[], size_t blocks_to_comp) {
  unsigned int i;
  SHA256_CTX ctx;
  SHA256_Init (&ctx);
  SHA256_Update (&ctx, counter, 8);
  for (i = 0; i < blocks_to_comp; i++)
    SHA256_Update (&ctx, blocks[i], BLOCK_SIZE);
  SHA256_Final (out, &ctx);
  *counter += 1;
}

int expand (uint64_t *counter, uint8_t *buf, size_t blocks_in_buf) {
  size_t i;
  const uint8_t *blocks[1] = { buf };
  uint8_t *cur = buf + BLOCK_SIZE;
  for (i = 1; i < blocks_in_buf; i++) { 
    compressb (counter, cur, blocks, 1);
    blocks[0] += BLOCK_SIZE;
    cur += BLOCK_SIZE;
  }
}

uint64_t bytes_to_littleend_uint64 (const uint8_t *bytes, size_t n_bytes) {
  int i;
  if (n_bytes > 8) 
    n_bytes = 8;
  uint64_t out = 0;
  for (i = n_bytes-1; i >= 0; i--) {
    out <<= 8;
    out |= bytes[i];
  }
  return out;
}

void * block_index (const struct hash_state *s, size_t i) {
  return s->buffer + (BLOCK_SIZE * i);
}

static uint64_t options_n_blocks (const struct balloon_options *opts) {
  const uint32_t bsize = BLOCK_SIZE;
  uint64_t ret = (opts->s_cost * 1024) / bsize;
  return (ret < BLOCKS_MIN) ? BLOCKS_MIN : ret;
}

void * block_last (const struct hash_state *s) {
  return block_index (s, s->n_blocks - 1);
}

int hash_state_init (struct hash_state *s, const struct balloon_options *opts, const uint8_t salt[SALT_LEN]) {
  s->counter = 0;
  s->n_blocks = options_n_blocks (opts);
  if (s->n_blocks % 2 != 0) s->n_blocks++;
  s->has_mixed = false;
  s->opts = opts;
  s->buffer = malloc (s->n_blocks * BLOCK_SIZE);
  int a = salt[0];
  a++;
  bitstream_init (&s->bstream);
  bitstream_seed_add (&s->bstream, salt, SALT_LEN);
  bitstream_seed_add (&s->bstream, &opts->s_cost, 8);
  bitstream_seed_add (&s->bstream, &opts->t_cost, 4);
  bitstream_seed_finalize (&s->bstream);
}

int hash_state_free (struct hash_state *s) {
  bitstream_free (&s->bstream);
  free (s->buffer);
}

int hash_state_fill (struct hash_state *s, const uint8_t salt[SALT_LEN], const uint8_t *in, size_t inlen) {
  SHA256_CTX c;
  SHA256_Init (&c);
  SHA256_Update (&c, &s->counter, 8);
  SHA256_Update (&c, salt, SALT_LEN);
  SHA256_Update (&c, in, inlen);
  SHA256_Update (&c, &s->opts->s_cost, 8);
  SHA256_Update (&c, &s->opts->t_cost, 4);
  SHA256_Final (s->buffer, &c);
  s->counter++;
  expand (&s->counter, s->buffer, s->n_blocks);
}

int hash_state_mix (struct hash_state *s, int32_t mixrounds) {
  size_t i, n;
  int32_t rounds;
  uint8_t buf[8];
  uint64_t neighbor;
  for (rounds=0; rounds < mixrounds; rounds++) {
   for (i = 0; i < s->n_blocks; i++) {
    uint8_t *cur_block = block_index (s, i);
    const size_t n_blocks_to_hash = 3;
    const uint8_t *blocks[2+n_blocks_to_hash];
    const uint8_t *prev_block = i ? cur_block - BLOCK_SIZE : block_last (s);
    blocks[0] = prev_block;
    blocks[1] = cur_block;
    for (n = 2; n < 2+n_blocks_to_hash; n++) {
      bitstream_fill_buffer (&s->bstream, buf, 8);
      neighbor = bytes_to_littleend_uint64 (buf, 8);
      blocks[n] = block_index (s, neighbor % s->n_blocks);
    }
    compressb (&s->counter, cur_block, blocks, 2+n_blocks_to_hash);
   }
   s->has_mixed = true;
  }
}

int hash_state_extract (const struct hash_state *s, uint8_t out[BLOCK_SIZE]) {
  uint8_t *b = block_last (s);
  memcpy ((char *)out, (const char *)b, BLOCK_SIZE);
}

#ifdef __cplusplus
}
#endif