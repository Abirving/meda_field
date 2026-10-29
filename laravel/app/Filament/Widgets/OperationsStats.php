<?php
namespace App\Filament\Widgets;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\{Field,Booking};
class OperationsStats extends StatsOverviewWidget {
 protected static ?string $pollingInterval='5s';
 protected function getStats():array {
  $fields=Field::visibleTo(auth()->user());$ids=(clone $fields)->pluck('id');$bookings=Booking::whereIn('field_id',$ids);$day=now('Africa/Addis_Ababa')->startOfDay()->utc();
  return [Stat::make('Fields in your jurisdiction',(clone $fields)->count()),Stat::make('Maintenance',(clone $fields)->where('status','MAINTENANCE')->count()),Stat::make('Today’s paid games',(clone $bookings)->where('start_time','>=',$day)->where('start_time','<',$day->copy()->addDay())->whereIn('status',['PAID','CHECKED_IN','COMPLETED'])->count()),Stat::make('Booked value · ETB',number_format((float)(clone $bookings)->whereIn('status',['PAID','CHECKED_IN','COMPLETED'])->sum('amount'),2))];
 }
}
