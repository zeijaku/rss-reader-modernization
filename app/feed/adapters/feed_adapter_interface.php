<?php

declare(strict_types=1);

/** Convert one supported XML feed dialect into the normalized feed contract. */
interface FeedAdapterInterface
{
    public function supports(SimpleXMLElement $xml): bool;

    /**
     * @return array{type:string,channel:array{title:string,link:?string,description:?string},item:list<NormalizedItem>}
     */
    public function parse(SimpleXMLElement $xml): array;
}
