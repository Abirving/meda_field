<?php
namespace App\Filament\Resources\FieldResource\Pages;
use App\Filament\Resources\FieldResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions;
class ManageFields extends ManageRecords {protected static string $resource=FieldResource::class;protected function getHeaderActions():array{return [Actions\CreateAction::make()];}}
