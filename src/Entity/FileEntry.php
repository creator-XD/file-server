<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'files')]
#[ORM\UniqueConstraint(name: 'uniq_files_user_path', columns: ['user_id', 'path'])]
class FileEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: FileBlob::class)]
    #[ORM\JoinColumn(name: 'blob_id', referencedColumnName: 'id', nullable: false)]
    private FileBlob $blob;

    #[ORM\Column(type: 'string', length: 1024)]
    private string $path;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'integer')]
    private int $size;

    #[ORM\Column(name: 'mime_type', type: 'string', length: 255, nullable: true)]
    private ?string $mimeType;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        User $user,
        FileBlob $blob,
        string $path,
        string $name,
        int $size,
        ?string $mimeType
    ) {
        $this->user = $user;
        $this->blob = $blob;
        $this->path = $path;
        $this->name = $name;
        $this->size = $size;
        $this->mimeType = $mimeType;

        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getBlob(): FileBlob
    {
        return $this->blob;
    }

    public function setBlob(FileBlob $blob): void
    {
        $this->blob = $blob;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): void
    {
        $this->mimeType = $mimeType;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}