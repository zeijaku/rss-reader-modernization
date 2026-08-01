<?php

declare(strict_types=1);

/** Feed取得処理の共通入口。 */
interface FeedTransportInterface
{
    /** @return array<string,mixed> */
    public function fetch(FeedSource $source, array $validators = []): array;
}
