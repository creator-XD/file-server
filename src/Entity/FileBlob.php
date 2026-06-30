<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'file_blobs')]
#[ORM\UniqueConstraint(name: 'uniq_file_blobs_hash', columns: ['hash'])]
#[ORM\UniqueConstraint(name: 'uniq_file_blobs_storage_key', columns: ['storage_key'])]
class FileBlob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $hash;

    #[ORM\Column(name: 'storage_key', type: 'string', length: 512)]
    private string $storageKey;

    #[ORM\Column(type: 'integer')]
    private int $size;

    #[ORM\Column(name: 'mime_type', type: 'string', length: 255, nullable: true)]
    private ?string $mimeType;

    #[ORM\Column(name: 'ref_count', type: 'integer')]
    private int $refCount = 1;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $hash, string $storageKey, int $size, ?string $mimeType)
    {
        $this->hash = $hash;
        $this->storageKey = $storageKey;
        $this->size = $size;
        $this->mimeType = $mimeType;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getRefCount(): int
    {
        return $this->refCount;
    }

    public function incrementRefCount(): void
    {
        $this->refCount++;
    }

    public function decrementRefCount(): void
    {
        if ($this->refCount > 0) {
            $this->refCount--;
        }
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}