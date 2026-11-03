{{--
    Storico messaggi di una conversazione ente<->adottante, reso nel contenuto del
    modale (sopra il box di risposta). Partial CONDIVISO dal pannello adottante e dal
    pannello ente: riceve il thread e il punto di vista del lettore corrente
    ($viewerIsAdopter) per allineare le bolle (i propri messaggi a destra).

    Sola lettura: nessuna mutazione qui. La risposta è il form del modale (Textarea)
    che delega a PostMessage; la lettura è marcata all'apertura (MarkThreadRead).

    @var \App\Models\MessageThread $thread
    @var bool $viewerIsAdopter   true se il lettore è l'adottante partecipante
--}}
<div
    class="fi-messaging-thread space-y-3 max-h-96 overflow-y-auto pr-1"
    wire:key="thread-{{ $thread->getKey() }}"
    x-data="messagingThread({{ $thread->getKey() }})"
>
    @forelse ($thread->messages as $message)
        @php
            // Il messaggio è "mio" se il mittente coincide col lato del lettore:
            // adottante ⇔ mittente = user_id del thread; ente ⇔ mittente ≠ user_id.
            $fromAdopter = (int) $message->user_id === (int) $thread->user_id;
            $mine = $viewerIsAdopter === $fromAdopter;
        @endphp

        <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}" wire:key="msg-{{ $message->getKey() }}">
            <div class="max-w-[80%] rounded-xl px-3 py-2 text-sm {{ $mine ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100' }}">
                <div class="mb-0.5 text-xs font-medium {{ $mine ? 'text-white/80' : 'text-gray-500 dark:text-gray-400' }}">
                    {{ $mine ? __('messaging.thread.you') : $message->user->name }}
                </div>
                <div class="whitespace-pre-line break-words">{{ $message->body }}</div>
                <div class="mt-1 text-[0.7rem] {{ $mine ? 'text-white/70' : 'text-gray-400' }}">
                    {{ optional($message->created_at)->isoFormat('D MMM YYYY, HH:mm') }}
                </div>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('messaging.thread.no_messages') }}</p>
    @endforelse
</div>
