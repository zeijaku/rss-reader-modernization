<?php

declare(strict_types=1);

require_once __DIR__ . '/item_identity.php';

/**
 * Source-agnostic representation of one fetched item.
 *
 * M1-D adds internal sourceItemId and ItemIdentity metadata while preserving
 * the original five-field API array returned by toArray().
 */
final class NormalizedItem
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $link,
        public readonly ?string $description,
        public readonly ?string $content,
        public readonly ?string $date,
        public readonly ?string $sourceItemId = null,
        public readonly ?ItemIdentity $identity = null
    ) {
    }

    public function withIdentity(ItemIdentity $identity): self
    {
        return new self(
            $this->title,
            $this->link,
            $this->description,
            $this->content,
            $this->date,
            $this->sourceItemId,
            $identity
        );
    }

    /**
     * Preserve the SB-15 public API contract. Internal identity metadata is
     * deliberately excluded until a separate API version defines it.
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
