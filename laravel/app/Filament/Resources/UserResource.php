<?php
namespace App\Filament\Resources;
use App\Models\{User,Location};
use Filament\Resources\Resource;
use Filament\Forms\{Form,Components\TextInput,Components\Select,Components\Toggle};
use Filament\Tables\{Table,Columns\TextColumn,Actions\EditAction};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
class UserResource extends Resource {
 protected static ?string $model=User::class;protected static ?string $navigationLabel='Staff & accounts';protected static ?string $navigationIcon='heroicon-o-users';
 public static function getEloquentQuery():Builder{$q=parent::getEloquentQuery();return auth()->user()->role==='SUPER_ADMIN'?$q:$q->where('role','OPERATOR')->whereIn('location_id',Location::visibleTo(auth()->user())->select('id'));}
 public static function form(Form $form):Form{return $form->schema([
  TextInput::make('first_name')->required()->maxLength(100),TextInput::make('last_name')->maxLength(100)->default(''),TextInput::make('username')->unique(ignoreRecord:true)->maxLength(100)->required(fn(?User $record)=>$record?->role!=='USER')->hidden(fn(?User $record)=>$record?->role==='USER'),TextInput::make('email')->email()->unique(ignoreRecord:true)->maxLength(150)->required(),
  \Filament\Forms\Components\Hidden::make('password')->default(fn()=>Str::random(64))->dehydrated(fn(string $operation)=>$operation==='create'),
  Select::make('role')->options(fn(?User $record)=>$record?->role==='USER'?['USER'=>'Customer']:(auth()->user()->role==='SUPER_ADMIN'?['OPERATOR'=>'Gatekeeper','WOREDA_ADMIN'=>'Woreda supervisor','SUBCITY_ADMIN'=>'Subcity supervisor','SUPER_ADMIN'=>'Super admin']:['OPERATOR'=>'Gatekeeper']))->disabled(fn(?User $record)=>$record?->role==='USER')->default('OPERATOR')->required(),
  Select::make('location_id')->label('Assigned woreda / subcity anchor')->options(fn()=>Location::visibleTo(auth()->user())->get()->pluck('label','id'))->required(fn(\Filament\Forms\Get $get)=>in_array($get('role'),['OPERATOR','WOREDA_ADMIN','SUBCITY_ADMIN'],true))->hidden(fn(?User $record)=>$record?->role==='USER'),Toggle::make('active')->default(true),
 ]);}
 public static function table(Table $table):Table{return $table->columns([TextColumn::make('first_name')->searchable(),TextColumn::make('last_name'),TextColumn::make('email'),TextColumn::make('role')->badge(),TextColumn::make('location.subcity'),TextColumn::make('location.woreda'),TextColumn::make('active')])->actions([EditAction::make()]);}
 public static function getPages():array{return ['index'=>UserResource\Pages\ManageUsers::route('/')];}
}
