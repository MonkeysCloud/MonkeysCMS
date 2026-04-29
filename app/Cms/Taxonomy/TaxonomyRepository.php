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

    public function persistTerm(TermEntity $t): TermEntity
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        if ($t->id !== null) {
            $this->pdo->prepare('UPDATE terms SET vocabulary_id=:v, parent_id=:p, name=:n, slug=:s, description=:d, metadata=:m, weight=:w, updated_at=:u WHERE id=:id')
                ->execute(['id'=>$t->id,'v'=>$t->vocabulary_id,'p'=>$t->parent_id,'n'=>$t->name,'s'=>$t->slug,'d'=>$t->description,'m'=>json_encode($t->metadata),'w'=>$t->weight,'u'=>$now]);
        } else {
            $this->pdo->prepare('INSERT INTO terms (vocabulary_id,parent_id,name,slug,description,metadata,weight,created_at,updated_at) VALUES (:v,:p,:n,:s,:d,:m,:w,:c,:u)')
                ->execute(['v'=>$t->vocabulary_id,'p'=>$t->parent_id,'n'=>$t->name,'s'=>$t->slug,'d'=>$t->description,'m'=>json_encode($t->metadata),'w'=>$t->weight,'c'=>$now,'u'=>$now]);
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
