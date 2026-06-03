<?php

namespace Travelopia\WordPress_AI\Dependencies\Http\Factory\Guzzle;

use Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\UploadedFile;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\UploadedFileFactoryInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\StreamInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\UploadedFileInterface;
class UploadedFileFactory implements UploadedFileFactoryInterface
{
    public function createUploadedFile(StreamInterface $stream, ?int $size = null, int $error = \UPLOAD_ERR_OK, ?string $clientFilename = null, ?string $clientMediaType = null): UploadedFileInterface
    {
        if ($size === null) {
            $size = $stream->getSize();
        }
        return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }
}
