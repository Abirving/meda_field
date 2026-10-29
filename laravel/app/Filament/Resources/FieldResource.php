<?php
namespace App\Filament\Resources;
use App\Models\{Field,Location,User};
use Filament\Resources\Resource;
use Filament\Forms\{Form,Components\TextInput,Components\Textarea,Components\Select};
use Filament\Tables\{Table,Columns\TextColumn,Actions\EditAction};
use Illuminate\Database\Eloquent\Builder;
class FieldResource extends Resource {
 protected static ?string $model=Field::class;protected static ?string $navigationIcon='heroicon-o-map';
 public static function getEloquentQuery():Builder{return parent::getEloquentQuery()->visibleTo(auth()->user())->with('location','operator');}
 public static function form(Form $form):Form{return $form->schema([
  TextInput::make('name')->required()->maxLength(150),Select::make('location_id')->label('Woreda')->options(fn()=>Location::visibleTo(auth()->user())->get()->pluck('label','id'))->required()->live(),
  Select::make('type')->options(['FOOTBALL'=>'Football','BASKETBALL'=>'Basketball'])->required(),
  TextInput::make('price_per_hour')->numeric()->minValue(1)->maxValue(100000)->step(0.01)->prefix('ETB')->required(),
  Select::make('operator_user_id')->label('Gatekeeper')->options(fn(\Filament\Forms\Get $get)=>User::where('role','OPERATOR')->where('active',true)->where('location_id',$get('location_id'))->get()->pluck('name','id'))->nullable(),
  TextInput::make('operating_hours.start')->label('Opens · EAT')->default('06:00')->required()->regex('/^([01][0-9]|2[0-3]):[0-5][0-9]$/'),TextInput::make('operating_hours.end')->label('Closes · EAT')->default('22:00')->required()->regex('/^([01][0-9]|2[0-3]):[0-5][0-9]$/'),
  \Filament\Forms\Components\Repeater::make('weekly_hours')->label('Weekly hours (leave empty to use daily hours)')->schema([
   Select::make('day')->options([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'])->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
   TextInput::make('start')->label('Opens EAT')->required()->regex('/^([01][0-9]|2[0-3]):[0-5][0-9]$/'),
   TextInput::make('end')->label('Closes EAT')->required()->regex('/^([01][0-9]|2[0-3]):[0-5][0-9]$/')
  ])->defaultItems(0)->maxItems(7)->formatStateUsing(fn($state)=>collect($state??[])->map(fn($hours,$day)=>$hours?array_merge(['day'=>(string)$day],$hours):null)->filter()->values()->all())->dehydrateStateUsing(fn($state)=>empty($state)?null:collect($state)->mapWithKeys(fn($row)=>[$row['day']=>['start'=>$row['start'],'end'=>$row['end']]])->all())->helperText('Days omitted from a weekly schedule are closed. Resolve future reservations before changing hours.'),
  TextInput::make('address')->maxLength(255),Textarea::make('description')->maxLength(2000),
 ]);}
 public static function table(Table $table):Table{return $table->columns([TextColumn::make('name')->searchable(),TextColumn::make('location.subcity'),TextColumn::make('location.woreda'),TextColumn::make('type'),TextColumn::make('status')->badge(),TextColumn::make('price_per_hour')->money('ETB'),TextColumn::make('operator.first_name')->label('Gatekeeper')])->actions([EditAction::make(),
 \Filament\Tables\Actions\Action::make('block')->label('Schedule closure')->form([
  \Filament\Forms\Components\DateTimePicker::make('start')->label('Starts EAT')->seconds(false)->required(),
  \Filament\Forms\Components\DateTimePicker::make('end')->label('Ends EAT')->seconds(false)->required()->after('start'),
  Textarea::make('reason')->minLength(5)->maxLength(250)->required()
  ])->requiresConfirmation()->modalDescription('Pending holds will be released. Paid reservations in the closure period will lose their pass and enter payment review.')->action(fn(Field $record,array $data)=>app(\App\Services\FieldOperations::class)->block(auth()->user(),$record,$data['start'],$data['end'],$data['reason'])),
 \Filament\Tables\Actions\Action::make('close')->label('Close field')->visible(fn(Field $r)=>$r->status==='ACTIVE')->form([Textarea::make('reason')->minLength(5)->maxLength(250)->required()])->requiresConfirmation()->action(function(Field $record,array $data){$record->closureReason=$data['reason'];$record->status='MAINTENANCE';$record->save();}),
 \Filament\Tables\Actions\Action::make('reopen')->visible(fn(Field $r)=>$r->status!=='ACTIVE')->requiresConfirmation()->action(fn(Field $record)=>$record->update(['status'=>'ACTIVE']))
 ]);}
 public static function getPages():array{return ['index'=>FieldResource\Pages\ManageFields::route('/')];}
}
