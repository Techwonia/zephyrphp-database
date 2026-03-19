<?php

declare(strict_types=1);

namespace ZephyrPHP\Database;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class Model
{
    protected array $fillable = [];
    protected array $guarded = ['id', 'createdAt', 'updatedAt', 'deletedAt'];
    protected array $hidden = [];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties();
        $data = [];

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($this);

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_object($value) && method_exists($value, 'getId')) {
                $value = $value->getId();
            }

            $data[$property->getName()] = $value;
        }

        $data = array_diff_key($data, array_flip($this->hidden));

        return $data;
    }

    public function fill(array $data): self
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->guarded, true)) continue;
            if (!empty($this->fillable) && !in_array($key, $this->fillable, true)) continue;
            $setter = 'set' . ucfirst($key);
            if (method_exists($this, $setter)) {
                $this->$setter($value);
            }
        }

        return $this;
    }

    public static function getEntityManager(): EntityManager
    {
        return EntityManager::getInstance();
    }

    public static function find(int $id): ?static
    {
        return static::getEntityManager()->find(static::class, $id);
    }

    /**
     * Find all records with a default safety limit to prevent memory exhaustion.
     *
     * @param int|null $limit Maximum rows to return (default 1000, null for unlimited)
     * @param int $offset Starting offset
     */
    public static function findAll(?int $limit = 1000, int $offset = 0): array
    {
        return static::getEntityManager()
            ->getRepository(static::class)
            ->findBy([], null, $limit, $offset);
    }

    public static function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return static::getEntityManager()
            ->getRepository(static::class)
            ->findBy($criteria, $orderBy, $limit, $offset);
    }

    public static function findOneBy(array $criteria): ?static
    {
        return static::getEntityManager()
            ->getRepository(static::class)
            ->findOneBy($criteria);
    }

    public static function count(array $criteria = []): int
    {
        return static::getEntityManager()
            ->getRepository(static::class)
            ->count($criteria);
    }

    public function save(): self
    {
        $em = static::getEntityManager();
        $em->persist($this);
        $em->flush();

        return $this;
    }

    public function delete(): void
    {
        $em = static::getEntityManager();
        $em->remove($this);
        $em->flush();
    }

    public function refresh(): self
    {
        static::getEntityManager()->em()->refresh($this);
        return $this;
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class);
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}
