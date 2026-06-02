@props([
    'title',
    'subtitle' => null,
    'data' => [],
    'empty' => 'No data for this period.',
    'format' => 'number',
    'type' => 'line',
])

@php
    use Deck\Deck\Presentation\FormatDuration;
    use Deck\Deck\Presentation\LineChartGeometry;

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

    $isBar = $type === 'bar';

    $barWidth = ($hasData && $chart !== null && count($chart['points']) > 0)
        ? max(2, (\Deck\Deck\Presentation\LineChartGeometry::WIDTH - \Deck\Deck\Presentation\LineChartGeometry::PAD_LEFT - \Deck\Deck\Presentation\LineChartGeometry::PAD_RIGHT) / count($chart['points']) * 0.62)
        : 8;
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)] [&_.deck-line-chart]:overflow-visible']) }}>
    <div class="border-b border-zinc-100 px-5 py-4">
        <h2 class="text-sm font-semibold text-zinc-900">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-1 text-xs text-zinc-500">{{ $subtitle }}</p>
        @endif
    </div>

    @if (! $hasData)
        <p class="px-5 py-10 text-center text-sm text-zinc-500">{{ $empty }}</p>
    @else
        <div
            x-data="deckLineChart({
                points: @js($chart['points']),
                width: {{ \Deck\Deck\Presentation\LineChartGeometry::WIDTH }},
                height: {{ \Deck\Deck\Presentation\LineChartGeometry::HEIGHT }},
            })"
            x-ref="container"
            class="deck-line-chart relative px-3 py-5 sm:px-5"
        >
            <div class="rounded-xl ring-1 ring-inset ring-zinc-200/70 p-2">
                <svg
                    x-ref="svg"
                    viewBox="0 0 {{ \Deck\Deck\Presentation\LineChartGeometry::WIDTH }} {{ \Deck\Deck\Presentation\LineChartGeometry::HEIGHT }}"
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
                            x1="{{ \Deck\Deck\Presentation\LineChartGeometry::PAD_LEFT }}"
                            y1="{{ $tick['y'] }}"
                            x2="{{ \Deck\Deck\Presentation\LineChartGeometry::WIDTH - \Deck\Deck\Presentation\LineChartGeometry::PAD_RIGHT }}"
                            y2="{{ $tick['y'] }}"
                            stroke="var(--deck-chart-grid)"
                            stroke-width="1"
                            vector-effect="non-scaling-stroke"
                        />
                        <text
                            x="{{ \Deck\Deck\Presentation\LineChartGeometry::PAD_LEFT - 8 }}"
                            y="{{ $tick['y'] + 4 }}"
                            text-anchor="end"
                            fill="var(--deck-chart-axis)"
                            class="text-[10px] font-medium"
                        >{{ $tick['label'] }}</text>
                    @endforeach

                    @if ($isBar)
                        @foreach ($chart['points'] as $point)
                            @if ($point['value'] > 0)
                                <rect
                                    x="{{ round($point['x'] - $barWidth / 2, 1) }}"
                                    y="{{ $point['y'] }}"
                                    width="{{ round($barWidth, 1) }}"
                                    height="{{ round($chart['baseline'] - $point['y'], 1) }}"
                                    fill="url(#deck-chart-fill-{{ $chartId }})"
                                    rx="2"
                                />
                                <rect
                                    x="{{ round($point['x'] - $barWidth / 2, 1) }}"
                                    y="{{ $point['y'] }}"
                                    width="{{ round($barWidth, 1) }}"
                                    height="2"
                                    fill="var(--deck-chart-line)"
                                    rx="1"
                                />
                            @endif
                        @endforeach
                    @else
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
                    @endif

                    @foreach ($chart['xTicks'] as $tick)
                        <text
                            x="{{ $tick['x'] }}"
                            y="{{ \Deck\Deck\Presentation\LineChartGeometry::HEIGHT - 6 }}"
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
                                y1="{{ \Deck\Deck\Presentation\LineChartGeometry::PAD_TOP }}"
                                y2="{{ $chart['baseline'] }}"
                                stroke="var(--deck-chart-crosshair)"
                                stroke-width="1"
                                stroke-dasharray="4 3"
                                vector-effect="non-scaling-stroke"
                            />
                            @if (! $isBar)
                                <circle
                                    :cx="active.x"
                                    :cy="active.y"
                                    r="4.5"
                                    fill="var(--deck-chart-line)"
                                    stroke="var(--deck-chart-point-ring)"
                                    stroke-width="2"
                                />
                            @endif
                        </g>
                    </template>
                </svg>
            </div>

            <div
                x-show="active"
                x-cloak
                :style="tooltipStyle"
                class="pointer-events-none absolute z-20 min-w-[8rem] max-w-[12rem] rounded-lg border border-zinc-200/80 bg-white/95 px-3 py-2 text-center shadow-lg backdrop-blur-sm"
            >
                <p class="text-[11px] font-medium text-zinc-500" x-text="active?.label"></p>
                <p class="mt-0.5 text-sm font-semibold tabular-nums text-zinc-900" x-text="active?.formatted"></p>
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
