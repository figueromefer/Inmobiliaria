<?php
namespace App\Services;
use Illuminate\Support\Collection;
class MinimumDocumentChecklistService {
 public function for(string $scope, Collection $documents): array { return collect(config("documentos_minimos.$scope", []))->map(fn($item)=>$item+['present'=>$documents->contains(fn($d)=>$d->tipo===$item['type'])])->all(); }
}
