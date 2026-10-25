<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Serializers\Abstract\GenericSerializer;
use Pvmlibs\FlexId\Serializers\Implementations\BaseSerializerPHP;
use Pvmlibs\FlexId\Serializers\Implementations\CustomSerializerFlexIdExt;
use Pvmlibs\FlexId\Tools\Alphabets;

/**
 * Serializes any number, supports only alphabet with power of 2 length.
 * Supports FlexId extension with fallback to PHP implementation.
 * If you want to use only FlexId extension, you can use CustomSerializerFlexIdExt directly instead.
 * This class exist mainly because it is faster with PHP implementation than CustomSerializer.
 * However, for FlexId extension it does not matter.
 * This is not intended for security. For confidentiality use with encryption.
 */
class BaseSerializer extends GenericSerializer
{
    public function __construct(
        string $alphabet = Alphabets::BASE32_SAFE_ALPHABET,
        bool $forcePHPImplementation = false,
    ) {
        // @codeCoverageIgnoreStart
        if (extension_loaded('flexid_utils') === false || $forcePHPImplementation) {
            $serializer = new BaseSerializerPHP($alphabet);
        } else {
            $serializer = new CustomSerializerFlexIdExt($alphabet);
        }
        // @codeCoverageIgnoreEnd

        parent::__construct($serializer);
    }
}
