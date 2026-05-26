## Available resolvers:

1. RedisTimestepWorkerResolver - uses Redis/Valkey as central source of information about current worker within current
   timestep. It practically guarantees unique ID generation - unless clock time on hosts will skip outside margins
   defined in resolver ($timestepExpireSec). With properly working NTP services this shouldn't happen. The most
   universal resolver for unique ID and most efficient in terms of 1 Redis request, can handle large number of worker
   requests - with default settings it's ~1 million/sec but in reality it will be limited by Redis (for single DB I got
   ~115k/script eval/s). If you need large number of requests/sec (like single ID generating in multiple FPM processes)
   you can use multiple Redis servers and use groupsBits, e.g. 10 servers will need groupsBits 4 and then assign
   appropriate groupId or take a look at StaticWorkerResolver.
   Memory usage is 16 bytes * timestepExpireSec / timestep[sek].
   Use case: large number of threads concurrently generating ID, generating in short-lived processes
   Approx max performance with default settings: ~100k ID/s/DB single gen, ~100M ID/s/DB in burst (100k workers)
   Can provide unique ID: yes

2. RedisReservedWorkerResolver - uses Redis/Valkey as central source of information about workers pool. It practically
   guarantees unique ID generation - unless clock time on hosts will skip outside margins defined in resolver
   (minimalWorkerSeparationMs). By default, has lower tolerance for servers clocks drifts than
   RedisTimestepWorkerResolver. Overhead for single Redis request is ~40% bigger than in RedisTimestepWorkerResolver,
   but it's meant for long processes so it will only perform up to 1 request every 10 seconds (by default) so what we
   gain is lower Redis usage in high generation ratio scenarios than in RedisTimestepWorkerResolver. The best use case
   is for not too large (<~1000), long-running application workers with intensive ID generation. Memory usage is liner
   to workersBits (10 bits - 49kB). As a rule of thumb, keep max workers (represented with workerBits) twice as big as
   actual workers generating IDs due to time needed for separation after used worker.
   Use case: predictable number of worker threads with intensive ID generation, long-lived processes
   Approx max performance with default settings: ~1k ID/s/DB single gen, ~1000M ID/s/DB in burst (1k workers)
   Can provide unique ID: yes

3. RandomWorkerResolver - works without any external dependency and guarantees uniqueness but only within one thread
   generating ids at a time. For many parallel threads probability of collisions is proportional to id generation rate:
   ids/sec * timestep[sec] / max workers, so e.g. at 1k id/s with workersBits 11 = 1000 * 0,000002048 ÷ 2048 = 0.0001%
   so 0,001 collisions/s - 1 every ~16min with that constant generation rate. Note that timestep depends on total bits
   used (workerBits + sequenceBits + groupsBits). Increasing sequenceBits or groupsBits will decrease entropy.
   Increasing workerBits above 11 bits will not give more entropy as timestep will be also bigger. These 11 bits cover
   the smallest quantum of time on system, which should be ~1us.
   Entropy here is smaller about 4.2x than in Snowflake - we exchange that for ID range, thus if you care
   about as low collisions as possible and not for ID range - go with Snowflake. You can use pidBits (they are part of
   workerBits) when working in one host environment. These can provide ID uniqueness but only if pid of processes can
   fit in pidBits without repetition and there is one thread within process. With 11 workerBits, 8 pidBits is
   reasonable. In multihost environment better leave 0 pidBits.
   Use case: can't use Redis, small applications or medium if some collisions won't be a problem or as fallback for
   other generators.
   Can provide unique ID: generally no, but can under some conditions.

4. StaticWorkerResolver - when you can provide arbitrary worker id, you can use this class. Worker id should be returned
   from workerHandlerFn() function. For unique IDs you need to make sure worker id will fit into workersBits, is unique
   within all working processes, and you provide same worker id to the same host/container (check class description
   why). It's preconfigured for many workers - up to 65k. Keep in mind, the more metadata bits, the less performance in
   burst (except sequenceBits to some extent).
   Use case: you have own method of resolving worker ID in sane range (like < 65k) that will provide ID uniqueness.
   Can provide unique ID: yes if provided unique worker id for each concurrent generator/thread and keep
   worker/host/container - worker id pair assignment.

5. ApcuTimestepWorkerResolver - similar to RedisTimestepWorkerResolver but uses APCu extension. It's shared memory
   between processes, but only when forked, so it can work for php-fpm workers or in some cli frameworks like hyperf.
   Nevertheless, it can only work within one host, for multi node environment it cannot guarantee uniqueness, unless you
   set distinct group id for each node.

There are also variations of above resolvers preconfigured to use with high timestamp bitshift to achieve shorter
timestamp based ID, prefixed with Short*.

All above resolvers are preconfigured for most common cases and should work performant out of the box, but you can
adjust bits for your needs. Check also code for the description of class parameters.
To save resources, generator will only request for worker on first ID generation.
The easiest way for unique ids is to go with some Redis based resolver.

