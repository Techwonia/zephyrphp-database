<?php

declare(strict_types=1);

namespace ZephyrPHP\Database;

use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;

class QueryBuilder
{
    private string $entityClass;
    private DoctrineQueryBuilder $qb;
    private string $alias;

    public function __construct(string $entityClass, string $alias = 'e')
    {
        $this->entityClass = $entityClass;
        $this->alias = $alias;

        $em = EntityManager::getInstance()->em();
        $this->qb = $em->createQueryBuilder()
            ->select($alias)
            ->from($entityClass, $alias);
    }

    public function select(string ...$columns): self
    {
        if (empty($columns)) {
            $this->qb->select($this->alias);
        } else {
            $selects = array_map(fn($col) => "{$this->alias}.{$col}", $columns);
            $this->qb->select($selects);
        }

        return $this;
    }

    public function where(string $field, string $operator, mixed $value): self
    {
        $paramName = 'p' . count($this->qb->getParameters());

        $this->qb->andWhere("{$this->alias}.{$field} {$operator} :{$paramName}")
            ->setParameter($paramName, $value);

        return $this;
    }

    public function orWhere(string $field, string $operator, mixed $value): self
    {
        $paramName = 'p' . count($this->qb->getParameters());

        $this->qb->orWhere("{$this->alias}.{$field} {$operator} :{$paramName}")
            ->setParameter($paramName, $value);

        return $this;
    }

    public function whereIn(string $field, array $values): self
    {
        $paramName = 'p' . count($this->qb->getParameters());

        $this->qb->andWhere("{$this->alias}.{$field} IN (:{$paramName})")
            ->setParameter($paramName, $values);

        return $this;
    }

    public function whereNotIn(string $field, array $values): self
    {
        $paramName = 'p' . count($this->qb->getParameters());

        $this->qb->andWhere("{$this->alias}.{$field} NOT IN (:{$paramName})")
            ->setParameter($paramName, $values);

        return $this;
    }

    public function whereNull(string $field): self
    {
        $this->qb->andWhere("{$this->alias}.{$field} IS NULL");
        return $this;
    }

    public function whereNotNull(string $field): self
    {
        $this->qb->andWhere("{$this->alias}.{$field} IS NOT NULL");
        return $this;
    }

    public function whereBetween(string $field, mixed $min, mixed $max): self
    {
        $paramMin = 'p' . count($this->qb->getParameters());
        $paramMax = 'p' . (count($this->qb->getParameters()) + 1);

        $this->qb->andWhere("{$this->alias}.{$field} BETWEEN :{$paramMin} AND :{$paramMax}")
            ->setParameter($paramMin, $min)
            ->setParameter($paramMax, $max);

        return $this;
    }

    public function whereLike(string $field, string $pattern): self
    {
        $paramName = 'p' . count($this->qb->getParameters());

        $this->qb->andWhere("{$this->alias}.{$field} LIKE :{$paramName}")
            ->setParameter($paramName, $pattern);

        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->qb->orderBy("{$this->alias}.{$field}", strtoupper($direction));
        return $this;
    }

    public function addOrderBy(string $field, string $direction = 'ASC'): self
    {
        $this->qb->addOrderBy("{$this->alias}.{$field}", strtoupper($direction));
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->qb->setMaxResults($limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->qb->setFirstResult($offset);
        return $this;
    }

    public function join(string $relation, string $alias): self
    {
        $this->qb->join("{$this->alias}.{$relation}", $alias);
        return $this;
    }

    public function leftJoin(string $relation, string $alias): self
    {
        $this->qb->leftJoin("{$this->alias}.{$relation}", $alias);
        return $this;
    }

    public function groupBy(string $field): self
    {
        $this->qb->groupBy("{$this->alias}.{$field}");
        return $this;
    }

    public function having(string $condition): self
    {
        $this->qb->having($condition);
        return $this;
    }

    public function get(): array
    {
        return $this->qb->getQuery()->getResult();
    }

    public function first(): ?object
    {
        $this->qb->setMaxResults(1);
        $results = $this->qb->getQuery()->getResult();

        return $results[0] ?? null;
    }

    public function count(): int
    {
        return (int) $this->qb
            ->select("COUNT({$this->alias}.id)")
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;

        $countQb = clone $this->qb;
        $total = (int) $countQb
            ->select("COUNT({$this->alias}.id)")
            ->getQuery()
            ->getSingleScalarResult();

        $items = $this->qb
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'data' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ];
    }

    public function toSql(): string
    {
        return $this->qb->getQuery()->getSQL();
    }

    public function getDql(): string
    {
        return $this->qb->getDQL();
    }

    public function getDoctrineQueryBuilder(): DoctrineQueryBuilder
    {
        return $this->qb;
    }
}
