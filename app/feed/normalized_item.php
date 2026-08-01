<?php

declare(strict_types=1);

/**
 * Source-agnostic representation of one fetched item.
 *
 * M1-A deliberately keeps this model small. It captures only fields already
 * present in the Secure Baseline RSS/Atom contract, so introducing the model
 * does not change the public API or browser behavior. Future source adapters
 * may populate the same shape without depending on RSS-specific XML details.
 */
final class NormalizedItem
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $link,
        public readonly ?string $description,
        public readonly ?string $content,
        public readonly ?string $date
    ) {
    }

    /**
     * Preserve the SB-15 array contract at the API boundary.
     *
     * @return array{title:string,link:?string,description:?string,content:?string,date:?string}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'link' => $this->link,
            'description' => $this->description,
            'content' => $this->content,
            'date' => $this->date,
        ];
    }
}
