<?php

declare(strict_types=1);

// this_file: paragra-php/src/Media/ImageOperationInterface.php

namespace ParaGra\Media;

interface ImageOperationInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function generate(MediaRequest $request, array $options = []): MediaResult;
}
