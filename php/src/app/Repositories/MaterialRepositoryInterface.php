<?php

namespace App\Repositories;

interface MaterialRepositoryInterface
{
    public function saveChunk(int $professionId, string $textChunk, array $vector): int;
    public function findNearestChunks(array $vector, int $professionId, int $limit = 3): array;
}
