## ID structure

ID uses 63 bits (sign bit is not used). There are 4 groups of bits:

```mermaid
block-beta
    block: ID
        A["timestamp 35-62 bits"]
        B["workers 0-20 bits"]
        C["sequence 0-30 bits"]
        D["groups 0-30"]
    end
```

ID lifespan range varies from 292 (default) to 292271 years, depending on timestamp bitshift config. Each group bits
count can vary with assumption that the sum of workers, sequence, groups bits and timestamp bitshift value must be <= 30.
Bits configuration and timestamp bitshift directly affects theoretic throughput, timestamp bitshift also defines max ID
lifespan.

Definitions:

1. metadata bits: workers, sequence and groups bits (max sum 30)
2. workers bits: defines maximum number of workers
3. sequence bits: defines maximum number of sequence
4. groups bits: defines logical groups e.g. datacenter, Redis server, distinct generator
5. timestep: a quantum of time defined by metadata bits count and timestamp bitshift:
6. timestamp: represents bits for timestamp part, represents number of nanoseconds shifted right by timestamp bitshift
7. timestamp bitshift: rotates timestamp right with n bits, effectively extending ID lifespan and reducing ID length.

Metadata bits influence timestamp precision and also defines timestep - the amount of time the timestamp will increment.
The more metadata bits, the bigger timestep, lower timestamp resolution and overall lower ID throughput.
For example:

1. with 10 workers bits, 8 sequence bits, 0 groups bits the timestep is 2^18 ns = 262144 ns ~= 0,26 ms. That
   means 1024 workers can generate 256 IDs within 0.26 ms. Then theoretical throughput per worker is ~984615 id/s.
2. with 8 workers bits, 12 sequence bits, 0 groups bits the timestep is 2^20 ns = 1048576 ns ~= 1,048 ms. That
   means 256 workers can generate 4096 IDs within 1,048 ms. Then theoretical throughput per worker is ~3908396 id/s.
3. with 8 workers bits, 12 sequence bits, 0 groups bits, timestamp bitshift 2 the timestep is 2^22 ns = 4194304 ns
   ~= 4,194 ms. That means 256 workers can generate 4096 IDs within 4,194 ms. Then theoretical throughput per worker is
   ~977099 id/s. Metadata bits are the same as in 2. but ID lifespan has extended to 4676 years from 292.

As you can see there are some tradeoffs between how many workers can generate how many IDs in quantum of time.
Groups bits are the last part of ID deliberately, this way you can mix different bits configuration needed for different
scenarios (e.g. small group of workers in burst mode and large group of processes with one time generation) and still
have unique guarantees, assuming only the same number of groups bits and different bits in that group between IDs.

Timestep can vary between 1-1073741824 ns, (max ~1,07s), although with using microtime() resolution is up to 1us,
so it won't make sense using less than 10 bits of metadata. Constant ID range and small biggest timestep allow for
reconfiguration even when generators are already used in production - in other ID generation solutions usually once
setup, you need to stick to initial configuration.