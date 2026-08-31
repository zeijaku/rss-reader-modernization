<?php

declare(strict_types=1);

interface RemoteFileProvider
{
    /** @return array{connected:bool,code:string} */
    public function testConnection(): array;

    /** @return list<array{name:string,path:string,type:string,size:?int,modified_at:?string}> */
    public function list(string $relativePath): array;

    /** @param resource $outputStream */
    public function download(string $relativePath, $outputStream, int $maxBytes): void;

    /** @param resource $inputStream */
    public function upload($inputStream, int $size, string $relativePath, bool $overwrite): void;

    public function mkdir(string $relativePath): void;

    public function move(string $fromRelativePath, string $toRelativePath, bool $overwrite): void;

    public function delete(string $relativePath, bool $directory): void;
}
