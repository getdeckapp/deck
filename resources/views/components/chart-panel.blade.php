@props([
    'title',
    'subtitle' => null,
    'data' => [],
    'empty' => 'No data for this period.',
    'format' => 'number',
])

@php
    use TorMorten\Deck\Support\FormatDuration;
    use TorMorten\Deck\Support\LineChartGeometry;

    $chartId = md5($title);
    $axisFormatter = match ($format) {
        'duration' => fn (int $value): string => FormatDuration::format($value),
        default => fn (int $value): string => (string) number_format($value),
    };

    $tooltipFormatter = match ($format) {
        'jobs' => fn (int $value): string => number_format($value).' '.str('job')->plural($value).' executed',
        'duration' => fn (int $value): string => FormatDuration::format($value).' average',
        default => $axisFormatter,
    };

    $hasData = collect($data)->sum('value') > 0;
    $chart = $hasData ? LineChartGeometry::build($data, $axisFormatter, $tooltipFormatter) : null;
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)] dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-[0_1px_2px_rgba(0,0,0,0.2),0_8px_32px_rgba(0,0,0,0.3)] [&_.deck-line-chart]:overflow-visible']) }}>
    <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $subtitle }}</p>
        @endif
    </div>

    @if (! $hasData)
        <p class="px-5 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ $empty }}</p>
    @else
        <div
            x-data="deckLineChart({
                points: @js($chart['points']),
                width: {{ \TorMorten\Deck\Support\LineChartGeometry::WIDTH }},
                height: {{ \TorMorten\Deck\Support\LineChartGeometry::HEIGHT }},
            })"
            x-ref="container"
            class="deck-line-chart relative px-3 py-5 sm:px-5"
        >
            <div class="rounded-xl ring-1 ring-inset ring-zinc-200/70 p-2 dark:ring-zinc-800/80">
                <svg
                    x-ref="svg"
                    viewBox="0 0 {{ \TorMorten\Deck\Support\LineChartGeometry::WIDTH }} {{ \TorMorten\Deck\Support\LineChartGeometry::HEIGHT }}"
                    class="w-full touch-none select-none"
                    preserveAspectRatio="xMidYMid meet"
                    @mousemove="move($event)"
                    @mouseleave="leave()"
                    @touchmove.prevent="move($event)"
                    @touchend="leave()"
                >
                    <defs>
                        <linearGradient id="deck-chart-fill-{{ $chartId }}" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" style="stop-color: var(--deck-chart-area-top)" />
                            <stop offset="100%" style="stop-color: var(--deck-chart-area-bottom)" />
                        </linearGradient>
                    </defs>

                    @foreach ($chart['yTicks'] as $tick)
                        <line
                            x1="{{ \TorMorten\Deck\Support\LineChartGeometry::PAD_LEFT }}"
                            y1="{{ $tick['y'] }}"
                            x2="{{ \TorMorten\Deck\Support\LineChartGeometry::WIDTH - \TorMorten\Deck\Support\LineChartGeometry::PAD_RIGHT }}"
                            y2="{{ $tick['y'] }}"
                            stroke="var(--deck-chart-grid)"
                            stroke-width="1"
                            vector-effect="non-scaling-stroke"
                        />
                        <text
                            x="{{ \TorMorten\Deck\Support\LineChartGeometry::PAD_LEFT - 8 }}"
                            y="{{ $tick['y'] + 4 }}"
                            text-anchor="end"
                            fill="var(--deck-chart-axis)"
                            class="text-[10px] font-medium"
                        >{{ $tick['label'] }}</text>
                    @endforeach

                    <path
                        d="{{ $chart['areaPath'] }}"
                        fill="url(#deck-chart-fill-{{ $chartId }})"
                    />

                    <path
                        d="{{ $chart['linePath'] }}"
                        fill="none"
                        stroke="var(--deck-chart-line)"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        vector-effect="non-scaling-stroke"
                    />

                    @foreach ($chart['xTicks'] as $tick)
                        <text
                            x="{{ $tick['x'] }}"
                            y="{{ \TorMorten\Deck\Support\LineChartGeometry::HEIGHT - 6 }}"
                            text-anchor="middle"
                            fill="var(--deck-chart-axis)"
                            class="text-[10px] font-medium"
                        >{{ $tick['label'] }}</text>
                    @endforeach

                    <template x-if="active">
                        <g>
                            <line
                                :x1="active.x"
                                :x2="active.x"
                                y1="{{ \TorMorten\Deck\Support\LineChartGeometry::PAD_TOP }}"
                                y2="{{ $chart['baseline'] }}"
                                stroke="var(--deck-chart-crosshair)"
                                stroke-width="1"
                                stroke-dasharray="4 3"
                                vector-effect="non-scaling-stroke"
                            />
                            <circle
                                :cx="active.x"
                                :cy="active.y"
                                r="4.5"
                                fill="var(--deck-chart-line)"
                                stroke="var(--deck-chart-point-ring)"
                                stroke-width="2"
                            />
                        </g>
                    </template>
                </svg>
            </div>

            <div
                x-show="active"
                x-cloak
                :style="tooltipStyle"
                class="pointer-events-none absolute z-20 min-w-[8rem] max-w-[12rem] rounded-lg border border-zinc-200/80 bg-white/95 px-3 py-2 text-center shadow-lg backdrop-blur-sm dark:border-zinc-600 dark:bg-zinc-800 dark:shadow-black/40"
            >
                <p class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400" x-text="active?.label"></p>
                <p class="mt-0.5 text-sm font-semibold tabular-nums text-zinc-900 dark:text-white" x-text="active?.formatted"></p>
            </div>
        </div>
    @endif
</div>

@once
    @push('deck-scripts')
        <script>
            function deckLineChart(config) {
                return {
                    points: config.points,
                    width: config.width,
                    height: config.height,
                    active: null,
                    tooltipStyle: {},
                    move(event) {
                        const svg = this.$refs.svg;
                        const svgRect = svg.getBoundingClientRect();
                        const clientX = event.touches?.[0]?.clientX ?? event.clientX;
                        const ratio = Math.max(0, Math.min(1, (clientX - svgRect.left) / svgRect.width));
                        const index = Math.round(ratio * (this.points.length - 1));
                        this.active = this.points[index];
                        this.positionTooltip();
                    },
                    positionTooltip() {
                        if (!this.active) {
                            return;
                        }

                        const svg = this.$refs.svg;
                        const containerRect = this.$refs.container.getBoundingClientRect();
                        const point = svg.createSVGPoint();
                        point.x = this.active.x;
                        point.y = this.active.y;
                        const screenPoint = point.matrixTransform(svg.getScreenCTM());
                        const left = screenPoint.x - containerRect.left;
                        const top = screenPoint.y - containerRect.top;
                        const flipBelow = this.active.y < this.height * 0.2;

                        this.tooltipStyle = {
                            left: `${left}px`,
                            top: `${top}px`,
                            transform: flipBelow
                                ? 'translate(-50%, 12px)'
                                : 'translate(-50%, calc(-100% - 10px))',
                        };
                    },
                    leave() {
                        this.active = null;
                    },
                };
            }
        </script>
    @endpush
@endonce
