<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Contracts\IntegerOperationsContract;
use Pvmlibs\FlexId\Serializers\Abstract\GenericSerializer;
use Pvmlibs\FlexId\Serializers\Implementations\CustomSerializerFlexIdExt;
use Pvmlibs\FlexId\Serializers\Implementations\CustomSerializerPHP;
use Pvmlibs\FlexId\Tools\Alphabets;

/**
 * Serialize any number (positive and negative) to string with custom length alphabet.
 * Supports FlexId extension with fallback to PHP implementation.
 * PHP implementation needs IntegerOperationsContract to support negative numbers, FlexId extension don't.
 * If you want to use only FlexId extension, you can also use CustomSerializerFlexIdExt directly instead.
 * This is not intended for security. For confidentiality use with encryption. If you want to just obfuscate id, HashSerializer
 * will be better.
 */
class CustomSerializer extends GenericSerializer
{
    /**
     * @param string $alphabet Supports lengths <2, 94>
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        ?IntegerOperationsContract $fullRangeIntegerOperations = null,
        string $alphabet = Alphabets::CUSTOM_SAFE_ALPHABET,
        bool $forcePHPImplementation = false,
    ) {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('flexid_utils') === false || $forcePHPImplementation) {
            $serializer = new CustomSerializerPHP($fullRangeIntegerOperations, $alphabet);
        } else {
            $serializer = new CustomSerializerFlexIdExt($alphabet);
        }
        // @codeCoverageIgnoreEnd

        parent::__construct($serializer);
    }
}
