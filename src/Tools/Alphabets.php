<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Tools;

class Alphabets
{
    /**
     * Shuffled alphabet with removed e, E, a, A, i, I, o, O, l, u, U, 0 to lower probability of building random words
     * and better distinction between chars. Max 12 chars output length per 64 bits. Use when user can see encoded values.
     */
    public const CUSTOM_SAFE_ALPHABET = 'd7x3Db2LY5Qgv1k9wZFfKMCztc6nPh4XyBsJ8VRpNSHTmqjGrW';

    /**
     * Semi-safe as not all vowels are removed (only A,a, O, o, 0) so some words may be randomly created.
     * 57 is minimal alphabet length to get max 11 characters output per 64. Use when smallest output is desired,
     * and a small risk of building random words can be taken.
     */
    public const CUSTOM_SEMI_SAFE = 'R9jHJScLBfFrKqTzXvgxtNbsp83DEkVMInym5hdGP47wQl26uYZUeW1iC';

    /**
     * Shuffled alphabet with removed vowels and digits to lower probability of building random words
     * and better distinction between chars. Max 13 chars output length.
     * Length is power of 2 so ~2 faster on PHP implementation than custom alphabet. Use when using PHP implementation
     * (no Flexid extension) with BaseSerializer and want as much speed as possible with safe alphabet.
     */
    public const BASE32_SAFE_ALPHABET = 'FDxkwdMKQRGgCBPLpYmvVJyXZbjczWqf';

    /**
     * Shuffled alphabet, can theoretically build random words. Max 11 chars output length.
     * Length is power of 2 so ~2 faster on PHP implementation than custom alphabet. Use when using PHP implementation
     * (no Flexid extension) with BaseSerializer and want as much speed as possible with some risk of building random words.
     */
    public const BASE64_ALPHABET = 'R9jHJSAcLBfFroKqTzXvgxtNbsp83DEkVMIn-y0m5hdG_P47awQl26uYZUeW1iCO';

    /*
      Function of alphabet length (as next steps) - max output length per 64 bits (full int range):
        2 - 64
        3 - 41
        4 - 32
        5 - 28
        6 - 25
        7 - 23
        8 - 22
        9 - 21
        10 - 20
        11 - 19
        12 - 18
        14 - 17
        16 - 16
        20 - 15
        24 - 14
        31 - 13
        41 - 12
        57 - 11
        85 - 10
        -------------
        Function of alphabet length (as next steps) - max output length per 63 bits (positive int range):
        9 - 20
        10 - 19
        12 - 18
        14 - 17
        16 - 16
        19 - 15
        23 - 14
        29 - 13
        39 - 12
        53 - 11
        79 - 10
     */
}
