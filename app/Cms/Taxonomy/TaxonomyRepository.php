<?php

declare(strict_types=1);

namespace App\Cms\Taxonomy;

use PDO;

/**
 * TaxonomyRepository — CRUD for vocabularies and terms.
 */
final class TaxonomyRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findVocabulary(string $machineName): ?VocabularyEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vocabularies WHERE machine_name = :name');
        $stmt->execute(['name' => $machineName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (new VocabularyEntity())->hydrate($row) : null;
    }

    /** @return VocabularyEntity[] */
    public function findAllVocabularies(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM vocabularies ORDER BY weight ASC, label ASC');
        return array_map(fn(array $r) => (new VocabularyEntity())->hydrate($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function persistVocabulary(VocabularyEntity $v): VocabularyEntity
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        if ($v->id !== null) {
            $this->pdo->prepare('UPDATE vocabularies SET machine_name=:mn, label=:l, description=:d, hierarchical=:h, multiple=:m, weight=:w, updated_at=:u WHERE id=:id')
                ->execute(['id'=>$v->id,'mn'=>$v->machine_name,'l'=>$v->label,'d'=>$v->description,'h'=>(int)$v->hierarchical,'m'=>(int)$v->multiple,'w'=>$v->weight,'u'=>$now]);
        } else {
            $this->pdo->prepare('INSERT INTO vocabularies (machine_name,label,description,hierarchical,multiple,weight,created_at,updated_at) VALUES (:mn,:l,:d,:h,:m,:w,:c,:u)')
                ->execute(['mn'=>$v->machine_name,'l'=>$v->label,'d'=>$v->description,'h'=>(int)$v->hierarchical,'m'=>(int)$v->multiple,'w'=>$v->weight,'c'=>$now,'u'=>$now]);
            $v->id = (int)$this->pdo->lastInsertId();
        }
        return $v;
    }

    public function findTerm(int $id): ?TermEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM terms WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (new TermEntity())->hydrate($row) : null;
    }

    /** @return TermEntity[] */
    public function findTermsByVocabulary(int $vocabId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM terms WHERE vocabulary_id = :vid ORDER BY weight ASC, name ASC');
        $stmt->execute(['vid' => $vocabId]);
        $terms = array_map(fn(array $r) => (new TermEntity())->hydrate($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
        return $this->buildTree($terms);
    }

    /** @return TermEntity[] */
    public function findTermsForNode(int $nodeId): array
    {
        $stmt = $this->pdo->prepare('SELECT t.* FROM terms t INNER JOIN node_terms nt ON t.id = nt.term_id WHERE nt.node_id = :nid ORDER BY t.name');
        $stmt->execute(['nid' => $nodeId]);
        return array_map(fn(array $r) => (new TermEntity())->hydrate($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find all published nodes associated with a given term.
     *
     * @return array<int, object>
     */
    public function findNodesByTerm(int $termId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.* FROM nodes n'
            . ' INNER JOIN node_terms nt ON n.id = nt.node_id'
            . ' WHERE nt.term_id = :tid AND n.status = :status AND n.deleted_at IS NULL'
            . ' ORDER BY n.created_at DESC'
            . ' LIMIT ' . (int) $limit
        );
        $stmt->execute(['tid' => $termId, 'status' => 'published']);

        return array_map(
            fn(array $r) => (new \App\Cms\Content\ContentEntity())->hydrate($r),
            $stmt->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    public function persistTerm(TermEntity $t): TermEntity
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        if ($t->id !== null) {
            $this->pdo->prepare('UPDATE terms SET vocabulary_id=:v, parent_id=:p, name=:n, slug=:s, description=:d, metadata=:m, weight=:w, language=:lang, updated_at=:u WHERE id=:id')
                ->execute(['id'=>$t->id,'v'=>$t->vocabulary_id,'p'=>$t->parent_id,'n'=>$t->name,'s'=>$t->slug,'d'=>$t->description,'m'=>json_encode($t->metadata),'w'=>$t->weight,'lang'=>$t->language,'u'=>$now]);
        } else {
            $this->pdo->prepare('INSERT INTO terms (vocabulary_id,parent_id,name,slug,description,metadata,weight,language,created_at,updated_at) VALUES (:v,:p,:n,:s,:d,:m,:w,:lang,:c,:u)')
                ->execute(['v'=>$t->vocabulary_id,'p'=>$t->parent_id,'n'=>$t->name,'s'=>$t->slug,'d'=>$t->description,'m'=>json_encode($t->metadata),'w'=>$t->weight,'lang'=>$t->language,'c'=>$now,'u'=>$now]);
            $t->id = (int)$this->pdo->lastInsertId();
        }
        return $t;
    }

    public function attachTermsToNode(int $nodeId, array $termIds): void
    {
        $this->pdo->prepare('DELETE FROM node_terms WHERE node_id = :nid')->execute(['nid' => $nodeId]);
        $ins = $this->pdo->prepare('INSERT INTO node_terms (node_id, term_id) VALUES (:nid, :tid)');
        foreach ($termIds as $tid) { $ins->execute(['nid' => $nodeId, 'tid' => (int)$tid]); }
    }

    // ── Vocabulary helpers ──────────────────────────────────────────────

    public function findVocabularyById(int $id): ?VocabularyEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vocabularies WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (new VocabularyEntity())->hydrate($row) : null;
    }

    /**
     * Delete a vocabulary and cascade-delete all its terms + node_terms.
     */
    public function deleteVocabulary(int $id): bool
    {
        // Remove node_terms for all terms in this vocabulary
        $this->pdo->prepare(
            'DELETE nt FROM node_terms nt INNER JOIN terms t ON nt.term_id = t.id WHERE t.vocabulary_id = :vid'
        )->execute(['vid' => $id]);

        // Remove terms
        $this->pdo->prepare('DELETE FROM terms WHERE vocabulary_id = :vid')->execute(['vid' => $id]);

        // Remove vocabulary
        $stmt = $this->pdo->prepare('DELETE FROM vocabularies WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Count terms in a vocabulary.
     */
    public function countTermsByVocabulary(int $vocabId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM terms WHERE vocabulary_id = :vid');
        $stmt->execute(['vid' => $vocabId]);
        return (int) $stmt->fetchColumn();
    }

    // ── Term helpers ────────────────────────────────────────────────────

    public function deleteTerm(int $id): bool
    {
        // Remove node associations
        $this->pdo->prepare('DELETE FROM node_terms WHERE term_id = :tid')->execute(['tid' => $id]);
        // Re-parent children to the deleted term's parent
        $term = $this->findTerm($id);
        if ($term) {
            $this->pdo->prepare('UPDATE terms SET parent_id = :pid WHERE parent_id = :id')
                ->execute(['pid' => $term->parent_id, 'id' => $id]);
        }
        $stmt = $this->pdo->prepare('DELETE FROM terms WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Bulk delete terms — re-parents children and cleans up node_terms.
     *
     * @param list<int> $ids
     */
    public function bulkDeleteTerms(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $affected = 0;
        foreach ($ids as $id) {
            if ($this->deleteTerm($id)) {
                $affected++;
            }
        }

        return $affected;
    }

    public function reorderTerms(array $weights): void
    {
        $stmt = $this->pdo->prepare('UPDATE terms SET weight = :w WHERE id = :id');
        foreach ($weights as $id => $weight) {
            $stmt->execute(['id' => $id, 'w' => $weight]);
        }
    }

    /**
     * Reorder and re-parent terms in a single batch.
     * @param list<array{id: int, weight: int, parent_id: int|null}> $items
     */
    public function reorderTree(array $items): void
    {
        $stmt = $this->pdo->prepare('UPDATE terms SET weight = :w, parent_id = :p WHERE id = :id');
        foreach ($items as $item) {
            $stmt->execute([
                'id' => (int) $item['id'],
                'w'  => (int) $item['weight'],
                'p'  => !empty($item['parent_id']) ? (int) $item['parent_id'] : null,
            ]);
        }
    }

    public function findTermBySlug(int $vocabId, string $slug): ?TermEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM terms WHERE vocabulary_id = :vid AND slug = :slug');
        $stmt->execute(['vid' => $vocabId, 'slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (new TermEntity())->hydrate($row) : null;
    }

    /**
     * Get a flat list of all terms (no tree) for a vocabulary.
     *
     * @return TermEntity[]
     */
    public function findTermsFlatByVocabulary(int $vocabId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM terms WHERE vocabulary_id = :vid ORDER BY weight ASC, name ASC');
        $stmt->execute(['vid' => $vocabId]);
        return array_map(fn(array $r) => (new TermEntity())->hydrate($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return TermEntity[] */
    private function buildTree(array $terms): array
    {
        $lookup = []; foreach ($terms as $t) { $lookup[$t->id] = $t; }
        $tree = [];
        foreach ($terms as $t) {
            if ($t->parent_id !== null && isset($lookup[$t->parent_id])) { $lookup[$t->parent_id]->children[] = $t; }
            else { $tree[] = $t; }
        }
        return $tree;
    }
}
