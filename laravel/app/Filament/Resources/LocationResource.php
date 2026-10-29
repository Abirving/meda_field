<?php
namespace App\Filament\Resources;
use App\Models\Location;
use Filament\Resources\Resource;
use Filament\Forms\{Form,Components\TextInput};
use Filament\Tables\{Table,Columns\TextColumn,Actions\EditAction};
use Illuminate\Database\Eloquent\Builder;
class LocationResource extends Resource {
 protected static ?string $model=Location::class;protected static ?string $navigationIcon='heroicon-o-building-office';
 public static function getEloquentQuery():Builder{return parent::getEloquentQuery()->visibleTo(auth()->user());}
 public static function form(Form $form):Form{return $form->schema([TextInput::make('region')->required()->maxLength(100),TextInput::make('subcity')->required()->maxLength(100),TextInput::make('woreda')->required()->maxLength(100)]);}
 public static function table(Table $table):Table{return $table->columns([TextColumn::make('region'),TextColumn::make('subcity')->searchable(),TextColumn::make('woreda')])->actions([EditAction::make()]);}
 public static function getPages():array{return ['index'=>LocationResource\Pages\ManageLocations::route('/')];}
}
