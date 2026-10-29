<div class="slot-grid">
@forelse($availability as $slot)
 @php($clock=$slot['start']->timezone(config('meda.timezone'))->format('H:i'))
 @if(!auth()->check() && $slot['status']==='available')
  <a class="slot available" href="{{ route('login',['field'=>$field->id,'date'=>$date]) }}"><strong>{{ $clock }}–{{ $slot['start']->addMinutes(60)->timezone(config('meda.timezone'))->format('H:i') }}</strong><small>Sign in to book</small></a>
 @else
  <button class="slot {{ $slot['status'] }}" type="submit" name="time" value="{{ $clock }}" @disabled($slot['status']!=='available')><strong>{{ $clock }}–{{ $slot['start']->addMinutes(60)->timezone(config('meda.timezone'))->format('H:i') }}</strong><small>{{ $slot['status']==='available'?'Hold for 5 minutes':($slot['status']==='hold'?'On hold':ucfirst($slot['status'])) }}</small></button>
 @endif
@empty<p>No play hours are available on this date.</p>@endforelse
</div>
