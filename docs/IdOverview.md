## ID overview

### ID examples for different timestamp bitshift in function of time

Overview table presenting generated ID with encoded and encrypted versions, using following configuration:
```php
$resolver = new StaticWorkerResolver(
    workerHandlerFn: fn() => 0,
    workersBits: 0,
    sequenceBits: 10,
    workerLockFilePath: null,
);
$generator = new FlexIdGenerator(workerResolver: $resolver);

$bench = new IdStats(
    generator: new FlexIdGenerator(workerResolver: $resolver),
    encoder: new RotatedAlphabetEncoder(),
    encrypter: new Sparx64Encrypter(
        secret: Sparx64Encrypter::generateSecret(),
        serializer: new NativeSerializer()
    ),
);

echo $bench->presentation(distribution: true);
```


| timestamp bit shift |Years left |Ending date  |Max ids/s    | Resolution[s] |Id type   |2026 (+0)           |2036 (+10)          |2076 (+50)          |2126 (+100)         |2276 (+250)|
|--------------------|-----------|-------------|-------------|---------------|----------|--------------------|--------------------|--------------------|--------------------|------------------
| 0                  |292.271    |2318-08-30   |1000000000   | 0.000001   |raw       |43581127276918784   |354621127276934144  |1598781127276945408 |3153981127276954624 |7819581127276963840
|                    |             |             |        |               |encoded   |sNy4hCLr4V (10)     |mSytzwgtVNv (11)    |YGZD3jXPZLb (11)    |tYGX4zYgL4r (11)    |ND1THJKS5XYh (12)   
|                    |             |             |        |               | encrypted     |yVyKqbkQDgYgR (13)  |zPXLxmJwBcBKG (13)  |QxLKfjkXqWfjB (13)  |JQvJzjyxvFbYQ (13)  |xXpLpyWmRmbbK (13)
| 2                  |1169.084   |3195-06-29   |250000000    | 0.000004   |raw       |10895281819244544   |88655281819246592   |399695281819249664  |788495281819251712  |1954895281819253760
|                    |             |             |        |               | encoded       |mW2vdQLDVk (10)     |H3Kc1zQdSZ (10)     |kd2QrYbwpgg (11)    |vxFC5fwS3M6 (11)    |Q2yJCyhj6X7 (11)    
|                    |             |             |        |               | encrypted     |CFcxXPwGPYcmP (13)  |kbVGqfCjfRMKR (13)  |zjZZkjgcKgpxM (13)  |PdQjKzddMjmMK (13)  |PCXKzxmcRQVcK (13)
| 4                  |4676.336   |6702-10-26   |62500000     | 0.000016   |raw       |2723820454813696    |22163820454814720   |99923820454814720   |197123820454815744  |488723820454815744
|                    |             |             |        |               | encoded       |jhXmPX4HcL (10)     |Kfd1Gd5mH8 (10)     |Kfd1GdWnB66 (11)    |mzJGXsBnb5g (11)    |mzJGXsBcK5k (11)    
|                    |             |             |        |               | encrypted     |VwyvzDqPLpLLG (13)  |BdZYjVKKkgGgP (13)  |kMgpfPQdMXdFQ (13)  |cVpXyKyYfLvxQ (13)  |fMybGBRfBwYfM (13)
| 6                  |18705.345  |20732-02-11  |15625000     | 0.000066   |raw       |680955113703424     |5540955113703424    |24980955113703424   |49280955113703424   |122180955113704448
|                    |             |             |        |               | encoded       |tsGcsCGVB (9)       |tsGcsCVYLF (10)     |tsGcsCGSbP (10)     |tsGcsCGqnS (10)     |rxqzyKqbS69 (11)    
|                    |             |             |        |               | encrypted     |FdLLfPQfgXCqB (13)  |DMKkxXLzMfYM (12)   |MQkzmwvBXYpLM (13)  |bYFxZvdYmQqBQ (13)  |kpZMQCLvwMZjP (13)
| 8                  |74821.382  |76849-04-20  |3906250      | 0.000262   |raw       |170238778425344     |1385238778425344    |6245238778425344    |12320238778425344   |30545238778425344
|                    |             |             |        |               | encoded       |m9h81zD61 (9)       |m9h81zkym (9)       |m9h81zDxFv (10)     |m9h81zDPt9 (10)     |m9h81zD2Nt (10)     
|                    |             |             |        |               | encrypted     |bkGPCdkYdXqKQ (13)  |FwykcCWGjMmkC (13)  |MJwwmqYgwLgKL (13)  |GvBCKbLMCzyyR (13)  |KvkxJKMPRMjRP (13)
| 10                 |292271.023 |294303-05-30 |976562       | 0.001049   |raw       |42559694605312      |346309694605312     |1561309694605312    |3080059694605312    |7636309694605312
|                    |             |             |        |               | encoded       |vtQtf5SCf (9)       |vtQtf5Xg6 (9)       |vtQtf5SwL (9)       |vtQtf5SQjf (10)     |vtQtf5SH1M (10)     
|                    |             |             |        |               | encrypted     |qBXGdBDyYkWv (12)   |cxyYfGmqKPKkC (13)  |wzZBRvkvpKCXM (13)  |PmZMqCkXgWYFk (13)  |VkmPZbBKCycBw (13)
| 12                 |292271.023 |294303-05-30 |244140       | 0.004194   |raw       |10639923650560      |86577423650816      |390327423650816     |770014923650048     |1909077423650816
|                    |             |             |        |               | encoded       |QHSKFPgm (8)        |wJhY2w5hM (9)       |wJhY2wWFP (9)       |rkt37gmWB (9)       |wJhY2wWvZ (9)       
|                    |             |             |        |               | encrypted     |yVzKbbfwpwyD (12)   |BcRjQyxCxjDKB (13)  |XJdZDzQcZZmXG (13)  |KZCKZXybbBBwL (13)  |qwCYDwXmpRdwB (13)
| 14                 |292271.023 |294303-05-30 |61035        | 0.016777   |raw       |2659980911616       |21644355911680      |97581855911936      |192503730911232     |477269355911168
|                    |             |             |        |               | encoded       |w7Bw94pC (8)        |4JwWr1Vh (8)        |8mK3xZ74Y (9)       |yqJFZySZc (9)       |FTsZwX94y (9)       
|                    |             |             |        |               | encrypted     |dXDXBYCkmGVwP (13)  |gVVmfKpjfyxY (12)   |QFkgLBKBcvLdx (13)  |GMWKPbYRJDFKG (13)  |qmVQkMxmWfzm (12)
| 16                 |292271.023 |294303-05-30 |15258        | 0.067109   |raw       |664995227648        |5411088977920       |24395463977984      |48125932727296      |119317338977280
|                    |             |             |        |               | encoded       |rwbc1S2 (7)         |KBw8WNMX (8)        |st26p46j (8)        |j7jZbY8ZL (9)       |4GH16ZRDb (9)       
|                    |             |             |        |               | encrypted     |FbfkBkfpMwZvw (13)  |YkgvMMdWCwwmd (13)  |VkzgJVzkjBPcd (13)  |qWDGQxZXjbCGK (13)  |GJLgLkdyYXCgP (13)
| 18                 |292271.023 |294303-05-30 |3814         | 0.268435   |raw       |166248806400        |1352772243456       |6098865993728       |12031483181056      |29829334743040
|                    |             |             |        |               | encoded       |dPCNWXQ (7)         |2fGnTP87 (8)        |PDnLrjKV (8)        |2MC9VdK9 (8)        |NRcbMXVw (8)        
|                    |             |             |        |               | encrypted     |gJZvQMxMMmbFg (13)  |gVfYfmygPXYYM (13)  |yfkgCyxfFBgkB (13)  |dWFMvqqLGFfzQ (13)  |wKkpwvbjCwdRR (13)
| 20                 |292271.023 |294303-05-30 |953          | 1.073742   |raw       |41562200064         |338193060864        |1524716497920       |3007870794752       |7457333685248
|                    |             |             |        |               | encoded       |kYVD2p5 (7)         |ktX7ZpP (7)         |KBt2hDC6 (8)        |x9V8hqvz (8)        |rFRBVnSz (8)        
|                    |             |             |        |               | encrypted     |GBgyvDpMLRBDg (13)  |cGyPGxKMPpPXk (13)  |bdgkkgckLyDVd (13)  |qWKBKLdbjcGRR (13)  |kGMcVpYyQBcRw (13)


### Example of integer ID after different transformation

Encoder                         |                         HexEncoder|                 FixedLengthEncoder|             RotatedAlphabetEncoder|
--------------------------------|-----------------------------------|-----------------------------------|-----------------------------------|
Example for ID 57439            |                               e05f|                   QQQQQQQQQQQQPQRd|                                pVg|
Example for ID 44275863723996160|                     9d4ca9d9647400|                   QQxKDGMxKxwDkDQQ|                         QBY7bz9BTy|


Encoding + signing ID (FastSigner, SipHash2-4) - fastest 64-bit sign:

Encoder                         |                         HexEncoder|                 FixedLengthEncoder|             RotatedAlphabetEncoder|
--------------------------------|-----------------------------------|-----------------------------------|-----------------------------------|
Example for ID 57439            |               e05fd565a68ef47ccc27|   QQQQQQQQQQQQPQRd4de4f2c039095285|                pVg999408ecd2349573|
Example for ID 44275863723996160|     9d4ca9d96474004bb128fa8e3d0505|   QQxKDGMxKxwDkDQQ013496af26f54f6c|         QBY7bz9BTy5fe6a9cf8f2c0615|


Encoding + signing ID (Signer, SipHash2-4, BCMathSerializer) - shortest 64-bit sign:

Encoder                         |                         HexEncoder|                 FixedLengthEncoder|             RotatedAlphabetEncoder|
--------------------------------|-----------------------------------|-----------------------------------|-----------------------------------|
Example for ID 57439            |                 e05f.YyjCvJzgjyXVB|     QQQQQQQQQQQQPQRd.JLKydRgZQRPRk|                  pVg.pymddJPFzWcvB|
Example for ID 44275863723996160|       9d4ca9d9647400.GXWMYkGWRRWqx|     QQxKDGMxKxwDkDQQ.CZLgjbbMKdZMR|           QBY7bz9BTy.DWPxYkkdLpWbM|


Encrypting ID:

Serializer                      |                      HexSerializer|              FixedLengthSerializer|                   NativeSerializer|                   BCMathSerializer|
--------------------------------|-----------------------------------|-----------------------------------|-----------------------------------|-----------------------------------|
Example for ID 57439            |                   a373a8769ac449dc|                   MgkgMBkwxMGDDxKG|                      vcQBKVmZjwXvw|                       GKhbxTVHdcD7|
Example for ID 44275863723996160|                   0c45becd8ba9c5f8|                   QGDRLPGKBLMxGRdB|                      dxkjCfMJgwqgC|                       h4b3RFmNS3jx|


Encrypting + signing ID (FastSigner, SipHash2-4) - fastest 64-bit sign:

Serializer                      |                      HexSerializer|              FixedLengthSerializer|                   NativeSerializer|                   BCMathSerializer|
--------------------------------|-----------------------------------|-----------------------------------|-----------------------------------|-----------------------------------|
Example for ID 57439            |   a373a8769ac449dc531734dc04dd5002|   MgkgMBkwxMGDDxKG9c9e09f12be3fbec|      vcQBKVmZjwXvwf527b129453a7fde|       GKhbxTVHdcD7d0e8ffa9050daa82|
Example for ID 44275863723996160|   0c45becd8ba9c5f874dcdc8ae114a056|   QGDRLPGKBLMxGRdBb05ee70125fd9545|      dxkjCfMJgwqgC4ebcd1a67e38014d|       h4b3RFmNS3jxaf702d1d0534f808|


Encrypting + signing ID (Signer, SipHash2-4, BCMathSerializer) - shortest 64-bit sign:

Serializer                      |                      HexSerializer|              FixedLengthSerializer|                   NativeSerializer|                   BCMathSerializer|
--------------------------------|-----------------------------------|-----------------------------------|-----------------------------------|-----------------------------------|
Example for ID 57439            |     a373a8769ac449dc.dyfVqCfMBDDjM|     MgkgMBkwxMGDDxKG.yzGLWdQpLWmwP|        vcQBKVmZjwXvw.XdKKWDqdgCDVw|         GKhbxTVHdcD7.wqzpYfBjvLQwQ|
Example for ID 44275863723996160|     0c45becd8ba9c5f8.qXGzFwCZLWZQk|     QGDRLPGKBLMxGRdB.fzzBdppVkgbJB|         dxkjCfMJgwqgC.YJfKcGkcCXzm|         h4b3RFmNS3jx.fCJZvjdWQKfzx|