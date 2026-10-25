<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Serializers\Abstract\GenericSerializer;
use Pvmlibs\FlexId\Serializers\Implementations\HashSerializerFlexIdExt;
use Pvmlibs\FlexId\Serializers\Implementations\HashSerializerPHP;
use Pvmlibs\FlexId\Tools\Alphabets;

/**
 * This serializer is intended especially for short, sequential id to hide their incremental nature and still create
 * short output. Encoding can be eventually reverted with some work so this is not secure solution, it will only look
 * random. For better confidentiality see encryption. Works with custom alphabets with length at least 32 characters,
 * the default one has optimized length and should not create words, but remember to use shuffled version before use.
 * Id capacity for output length with default alphabet length:
 * 3 chars: ~65k
 * 4 chars: ~4 millions
 * 5 chars: ~250 millions
 * 6 chars: ~13 billions.
 */
class HashSerializer extends GenericSerializer
{
    /**
     * @param string $alphabet  pass your own shuffled alphabet, otherwise it will be easy to reverse encoded id
     * @param int    $minLength Minimum length of encoded id. This will work for >=4, lower values can return 1-3 chars output.
     *                          This will truncate upper range proportionally, you can check max range with getMaxRange().
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        #[\SensitiveParameter]
        string $alphabet = Alphabets::CUSTOM_SAFE_ALPHABET,
        int $minLength = 4,
        bool $forcePHPImplementation = false,
    ) {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('flexid_utils') === false || $forcePHPImplementation) {
            $serializer = new HashSerializerPHP($alphabet, $minLength);
        } else {
            $serializer = new HashSerializerFlexIdExt($alphabet, $minLength);
        }
        // @codeCoverageIgnoreEnd

        parent::__construct($serializer);
    }
}
