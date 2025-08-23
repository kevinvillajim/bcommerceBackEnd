<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\CategoryId;
use App\Domain\ValueObjects\Slug;
use DateTime;
use DateTimeInterface;

/**
 * Category Entity
 *
 * Core domain entity representing a product category in the system.
 */
class CategoryEntity
{
    private ?CategoryId $id;

    private string $name;

    private Slug $slug;

    private ?string $description;

    private ?CategoryId $parentId;

    private ?string $icon;

    private ?string $image;

    private ?int $order;

    private bool $isActive;

    private bool $featured;

    private ?DateTimeInterface $createdAt;

    private ?DateTimeInterface $updatedAt;

    // Collections and relationships
    private array $children = [];

    private ?int $productCount = null;

    /**
     * Category constructor.
     */
    public function __construct(
        string $name,
        Slug $slug,
        ?string $description = null,
        ?CategoryId $parentId = null,
        ?string $icon = null,
        ?string $image = null,
        ?int $order = null,
        bool $isActive = true,
        bool $featured = false,
        ?CategoryId $id = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null
    ) {
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->parentId = $parentId;
        $this->icon = $icon;
        $this->image = $image;
        $this->order = $order;
        $this->isActive = $isActive;
        $this->featured = $featured;
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Creates a new Category from primitive values.
     */
    public static function create(
        string $name,
        string $slug,
        ?string $description = null,
        ?int $parentId = null,
        ?string $icon = null,
        ?string $image = null,
        ?int $order = null,
        bool $isActive = true,
        bool $featured = false
    ): self {
        return new self(
            $name,
            new Slug($slug),
            $description,
            $parentId !== null ? new CategoryId($parentId) : null,
            $icon,
            $image,
            $order,
            $isActive,
            $featured,
            null,
            new DateTime,
            new DateTime
        );
    }

    /**
     * Reconstitutes a Category from storage.
     */
    public static function reconstitute(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        ?int $parentId,
        ?string $icon,
        ?string $image,
        ?int $order,
        bool $isActive,
        bool $featured,
        string $createdAt,
        string $updatedAt
    ): self {
        return new self(
            $name,
            new Slug($slug),
            $description,
            $parentId !== null ? new CategoryId($parentId) : null,
            $icon,
            $image,
            $order,
            $isActive,
            $featured,
            new CategoryId($id),
            new DateTime($createdAt),
            new DateTime($updatedAt)
        );
    }

    // Getters

    public function getId(): ?CategoryId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): Slug
    {
        return $this->slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getParentId(): ?CategoryId
    {
        return $this->parentId;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    // ✅ MÉTODOS AGREGADOS para compatibilidad con controladores
    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function getFeatured(): bool
    {
        return $this->featured;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getProductCount(): ?int
    {
        return $this->productCount;
    }

    // Setters and methods

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->updateTimestamp();
    }

    public function setSlug(string $slug): void
    {
        $this->slug = new Slug($slug);
        $this->updateTimestamp();
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->updateTimestamp();
    }

    public function setParentId(?int $parentId): void
    {
        $this->parentId = $parentId !== null ? new CategoryId($parentId) : null;
        $this->updateTimestamp();
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
        $this->updateTimestamp();
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
        $this->updateTimestamp();
    }

    public function setOrder(?int $order): void
    {
        $this->order = $order;
        $this->updateTimestamp();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updateTimestamp();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updateTimestamp();
    }

    public function markAsFeatured(): void
    {
        $this->featured = true;
        $this->updateTimestamp();
    }

    public function unmarkAsFeatured(): void
    {
        $this->featured = false;
        $this->updateTimestamp();
    }

    public function setChildren(array $children): void
    {
        $this->children = $children;
    }

    public function setProductCount(int $count): void
    {
        $this->productCount = $count;
    }

    public function addChild(CategoryEntity $child): void
    {
        $this->children[] = $child;
    }

    /**
     * Updates the timestamp when the entity is modified.
     */
    private function updateTimestamp(): void
    {
        $this->updatedAt = new DateTime;
    }

    /**
     * Converts entity to an array representation.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id ? $this->id->getValue() : null,
            'name' => $this->name,
            'slug' => $this->slug->getValue(),
            'description' => $this->description,
            'parent_id' => $this->parentId ? $this->parentId->getValue() : null,
            'icon' => $this->icon,
            'image' => $this->image,
            'order' => $this->order,
            'is_active' => $this->isActive,
            'featured' => $this->featured,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
