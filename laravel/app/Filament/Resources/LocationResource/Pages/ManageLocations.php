<?php
namespace App\Filament\Resources\LocationResource\Pages;
use App\Filament\Resources\LocationResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions;
class ManageLocations extends ManageRecords {protected static string $resource=LocationResource::class;protected function getHeaderActions():array{return [Actions\CreateAction::make()];}}
