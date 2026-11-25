{{-- Blade view for Wire. --}}
<section class="hf-lp-wire" data-testid="landing-wire" aria-label="Sample matchday announcements">
    <span class="hf-lp-wire__label"><i class="bi bi-broadcast" aria-hidden="true"></i> Matchday wire</span>

    <div class="hf-lp-wire__viewport">
        <div class="hf-lp-wire__track">
            <div class="hf-lp-wire__group">
                @foreach ($announcements as $announcement)
                    <span class="hf-lp-wire__item">
                        <span class="hf-lp-wire__ball" aria-hidden="true"></span>
                        {{ $announcement }}
                    </span>
                @endforeach
            </div>
            <div class="hf-lp-wire__group" aria-hidden="true">
                @foreach ($announcements as $announcement)
                    <span class="hf-lp-wire__item">
                        <span class="hf-lp-wire__ball"></span>
                        {{ $announcement }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</section>
