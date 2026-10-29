<?php
namespace App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions;
class ManageUsers extends ManageRecords {protected static string $resource=UserResource::class;protected function getHeaderActions():array{return [Actions\CreateAction::make()->label('Invite staff')->after(fn(\App\Models\User $record)=>\Illuminate\Support\Facades\Password::sendResetLink(['email'=>$record->email]))];}}
