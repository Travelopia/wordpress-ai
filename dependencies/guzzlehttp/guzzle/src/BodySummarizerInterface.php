<?php

namespace Travelopia\WordPress_AI\Dependencies\GuzzleHttp;

use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
