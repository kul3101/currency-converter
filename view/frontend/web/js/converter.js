/**
 * Currency converter widget, initialised through data-mage-init on the converter template.
 */
define(['mage/translate'], function ($t) {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg',
        CHART_HEIGHT = 280,
        PAD = {top: 16, right: 16, bottom: 32},
        Y_TICKS = 4;

    function format(template) {
        var args = Array.prototype.slice.call(arguments, 1);

        return template.replace(/%(\d+)/g, function (match, index) {
            var value = args[Number(index) - 1];

            return value === undefined ? match : value;
        });
    }

    function svgEl(name, attrs) {
        var el = document.createElementNS(SVG_NS, name);

        Object.keys(attrs || {}).forEach(function (key) {
            el.setAttribute(key, attrs[key]);
        });

        return el;
    }

    function pluckRates(history) {
        return history.map(function (point) {
            return point.rate;
        });
    }

    function Converter(root, config) {
        this.root = root;
        this.config = config;
        this.locale = document.documentElement.lang || undefined;
        this.data = null;
        this.request = null;
        this.els = {
            form: root.querySelector('[data-role="form"]'),
            amount: root.querySelector('[data-role="amount"]'),
            from: root.querySelector('[data-role="from"]'),
            to: root.querySelector('[data-role="to"]'),
            swap: root.querySelector('[data-role="swap"]'),
            converted: root.querySelector('[data-role="converted"]'),
            rate: root.querySelector('[data-role="rate"]'),
            meta: root.querySelector('[data-role="meta"]'),
            error: root.querySelector('[data-role="error"]'),
            stats: root.querySelector('[data-role="stats"]'),
            chart: root.querySelector('[data-role="chart"]'),
            ranges: Array.prototype.slice.call(root.querySelectorAll('[data-range]'))
        };

        this.state = this.readUrlState();
        this.bind();
        this.syncControls();
        this.load();
    }

    Converter.prototype.readUrlState = function () {
        var params = new URLSearchParams(window.location.search),
            from = (params.get('from') || '').toUpperCase(),
            to = (params.get('to') || '').toUpperCase(),
            range = params.get('range'),
            amount = parseFloat(params.get('amount')),
            has = function (select, code) {
                return !!code && !!select.querySelector('option[value="' + CSS.escape(code) + '"]');
            };

        return {
            from: has(this.els.from, from) ? from : this.config.from,
            to: has(this.els.to, to) ? to : this.config.to,
            range: this.config.ranges.indexOf(range) !== -1 ? range : this.config.range,
            amount: isFinite(amount) && amount >= 0 ? amount : 1
        };
    };

    Converter.prototype.writeUrlState = function () {
        var params = new URLSearchParams(window.location.search);

        params.set('from', this.state.from);
        params.set('to', this.state.to);
        params.set('range', this.state.range);
        params.set('amount', String(this.state.amount));
        window.history.replaceState(null, '', window.location.pathname + '?' + params.toString());
    };

    Converter.prototype.bind = function () {
        var self = this;

        this.els.form.addEventListener('submit', function (event) {
            event.preventDefault();
        });
        this.els.from.addEventListener('change', function () {
            self.state.from = self.els.from.value;
            self.load();
        });
        this.els.to.addEventListener('change', function () {
            self.state.to = self.els.to.value;
            self.load();
        });
        this.els.swap.addEventListener('click', this.swap.bind(this));
        this.els.amount.addEventListener('input', this.onAmountInput.bind(this));
        this.els.ranges.forEach(function (button) {
            button.addEventListener('click', function () {
                self.selectRange(button.getAttribute('data-range'));
            });
        });
        this.observeResize();
    };

    Converter.prototype.swap = function () {
        var from = this.state.from;

        this.state.from = this.state.to;
        this.state.to = from;
        this.syncControls();
        this.load();
    };

    Converter.prototype.onAmountInput = function () {
        var value = parseFloat(this.els.amount.value);

        this.state.amount = isFinite(value) && value >= 0 ? value : NaN;
        this.renderResult();
        if (!isNaN(this.state.amount)) {
            this.writeUrlState();
        }
    };

    Converter.prototype.selectRange = function (range) {
        if (this.state.range === range) {
            return;
        }
        this.state.range = range;
        this.syncControls();
        this.load();
    };

    Converter.prototype.observeResize = function () {
        var self = this,
            lastWidth = 0;

        if (!window.ResizeObserver) {
            return;
        }
        new ResizeObserver(function () {
            var width = self.els.chart.clientWidth;

            if (width !== lastWidth) {
                lastWidth = width;
                self.renderChart();
            }
        }).observe(this.els.chart);
    };

    Converter.prototype.syncControls = function () {
        var range = this.state.range;

        this.els.from.value = this.state.from;
        this.els.to.value = this.state.to;
        this.els.amount.value = String(this.state.amount);
        this.els.ranges.forEach(function (button) {
            button.setAttribute('aria-pressed', button.getAttribute('data-range') === range ? 'true' : 'false');
        });
    };

    Converter.prototype.buildRateUrl = function () {
        var url = new URL(this.config.rateUrl, window.location.href);

        url.searchParams.set('from', this.state.from);
        url.searchParams.set('to', this.state.to);
        url.searchParams.set('range', this.state.range);

        return url.toString();
    };

    Converter.prototype.load = function () {
        var self = this;

        if (this.request) {
            this.request.abort();
        }
        this.request = new AbortController();
        this.writeUrlState();
        this.setLoading(true);

        fetch(this.buildRateUrl(), {
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            credentials: 'same-origin',
            signal: this.request.signal
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (body) {
                if (!response.ok || body.error) {
                    throw new Error(body.message || $t('Unable to load the exchange rate. Please try again.'));
                }

                return body;
            });
        }).then(function (body) {
            self.showData(body, '');
        }).catch(function (error) {
            if (error.name !== 'AbortError') {
                self.showData(null, error.message || $t('Unable to load the exchange rate. Please try again.'));
            }
        });
    };

    Converter.prototype.showData = function (data, errorMessage) {
        this.data = data;
        this.setLoading(false);
        this.els.error.textContent = errorMessage;
        this.els.error.hidden = !errorMessage;
        this.renderResult();
        this.renderStats();
        this.renderChart();
    };

    Converter.prototype.setLoading = function (loading) {
        this.root.classList.toggle('is-loading', loading);
        this.els.chart.setAttribute('aria-busy', loading ? 'true' : 'false');
        if (loading && !this.data) {
            this.els.meta.textContent = $t('Loading exchange rate…');
        }
    };

    Converter.prototype.formatRate = function (value) {
        var options = Math.abs(value) >= 1 ? {maximumFractionDigits: 4} : {maximumSignificantDigits: 6};

        return new Intl.NumberFormat(this.locale, options).format(value);
    };

    Converter.prototype.formatAmount = function (value) {
        var options = Math.abs(value) >= 1 || value === 0 ?
            {minimumFractionDigits: 2, maximumFractionDigits: 2} :
            {maximumSignificantDigits: 6};

        return new Intl.NumberFormat(this.locale, options).format(value);
    };

    Converter.prototype.formatDate = function (iso, short) {
        var parts = iso.split('-'),
            date = new Date(Date.UTC(+parts[0], +parts[1] - 1, +parts[2])),
            options = {year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC'};

        if (short) {
            options = this.state.range === '5y' ?
                {year: 'numeric', month: 'short', timeZone: 'UTC'} :
                {month: 'short', day: 'numeric', timeZone: 'UTC'};
        }

        return new Intl.DateTimeFormat(this.locale, options).format(date);
    };

    Converter.prototype.renderResult = function () {
        var data = this.data,
            amount = this.state.amount;

        if (!data) {
            this.els.converted.innerHTML = '&nbsp;';
            this.els.rate.innerHTML = '&nbsp;';
            this.els.meta.innerHTML = '&nbsp;';

            return;
        }

        this.els.converted.textContent = isNaN(amount) ?
            $t('Enter a valid amount.') :
            this.formatAmount(amount) + ' ' + data.from + ' = ' +
                this.formatAmount(amount * data.rate) + ' ' + data.to;
        this.els.rate.textContent = '1 ' + data.from + ' = ' + this.formatRate(data.rate) + ' ' + data.to +
            '  ·  1 ' + data.to + ' = ' + this.formatRate(1 / data.rate) + ' ' + data.from;
        this.els.meta.textContent = format($t('Rate as of %1'), this.formatDate(data.date));
    };

    Converter.prototype.renderStats = function () {
        var stats = this.els.stats,
            history = this.data ? this.data.history : [],
            rates, change, trend;

        stats.textContent = '';
        if (history.length < 2) {
            return;
        }

        rates = pluckRates(history);
        change = (rates[rates.length - 1] - rates[0]) / rates[0] * 100;
        trend = change > 0 ? 'up' : change < 0 ? 'down' : '';
        [
            [$t('Change'), (change > 0 ? '+' : '') + change.toFixed(2) + '%', trend],
            [$t('High'), this.formatRate(Math.max.apply(null, rates)), ''],
            [$t('Low'), this.formatRate(Math.min.apply(null, rates)), '']
        ].forEach(function (item) {
            var wrapper = document.createElement('div'),
                dt = document.createElement('dt'),
                dd = document.createElement('dd');

            dt.textContent = item[0];
            dd.textContent = item[1];
            if (item[2]) {
                dd.className = 'is-' + item[2];
            }
            wrapper.appendChild(dt);
            wrapper.appendChild(dd);
            stats.appendChild(wrapper);
        });
    };

    Converter.prototype.renderChart = function () {
        var container = this.els.chart,
            history = this.data ? this.data.history : [],
            empty, scale, svg;

        container.textContent = '';
        if (!this.data) {
            return;
        }
        if (history.length < 2) {
            empty = document.createElement('p');
            empty.className = 'sidelah-cc__empty';
            empty.textContent = $t('No historical data is available for this pair and range.');
            container.appendChild(empty);

            return;
        }

        scale = this.buildScale(history, Math.max(container.clientWidth, 280));
        svg = svgEl('svg', {
            viewBox: '0 0 ' + scale.width + ' ' + CHART_HEIGHT,
            width: scale.width,
            height: CHART_HEIGHT,
            role: 'img',
            tabindex: '0',
            'aria-label': format($t('Exchange rate history for %1 to %2'), this.data.from, this.data.to)
        });

        this.drawAxes(svg, scale, history);
        this.drawSeries(svg, scale, history);
        container.appendChild(svg);
        this.attachTooltip(svg, scale, history);
    };

    Converter.prototype.buildScale = function (history, width) {
        var rates = pluckRates(history),
            min = Math.min.apply(null, rates),
            max = Math.max.apply(null, rates),
            spread = max - min || Math.abs(max) * 0.01 || 1,
            step, decimals, axisFormat, labels, padLeft, plotW, plotH, i;

        min -= spread * 0.08;
        max += spread * 0.08;
        step = (max - min) / Y_TICKS;
        decimals = Math.min(10, Math.max(0, 1 - Math.floor(Math.log10(step))));
        axisFormat = new Intl.NumberFormat(this.locale, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
        labels = [];
        for (i = 0; i <= Y_TICKS; i++) {
            labels.push(axisFormat.format(min + step * i));
        }
        padLeft = 14 + 6.5 * Math.max.apply(null, labels.map(function (label) {
            return label.length;
        }));
        plotW = width - padLeft - PAD.right;
        plotH = CHART_HEIGHT - PAD.top - PAD.bottom;

        return {
            width: width,
            padLeft: padLeft,
            plotW: plotW,
            plotH: plotH,
            min: min,
            step: step,
            labels: labels,
            x: function (index) {
                return padLeft + index / (history.length - 1) * plotW;
            },
            y: function (rate) {
                return PAD.top + (1 - (rate - min) / (max - min)) * plotH;
            }
        };
    };

    Converter.prototype.drawAxes = function (svg, scale, history) {
        var tickCount = Math.min(history.length, scale.width < 480 ? 3 : 5),
            i, gy, label, index, anchor, tick;

        for (i = 0; i <= Y_TICKS; i++) {
            gy = scale.y(scale.min + scale.step * i);
            svg.appendChild(svgEl('line', {
                x1: scale.padLeft, x2: scale.width - PAD.right, y1: gy, y2: gy, 'class': 'sidelah-cc__grid'
            }));
            label = svgEl('text', {
                x: scale.padLeft - 8, y: gy + 4, 'text-anchor': 'end', 'class': 'sidelah-cc__axis'
            });
            label.textContent = scale.labels[i];
            svg.appendChild(label);
        }

        for (i = 0; i < tickCount; i++) {
            index = Math.round(i * (history.length - 1) / (tickCount - 1));
            anchor = i === 0 ? 'start' : i === tickCount - 1 ? 'end' : 'middle';
            tick = svgEl('text', {
                x: scale.x(index), y: CHART_HEIGHT - 8, 'text-anchor': anchor, 'class': 'sidelah-cc__axis'
            });
            tick.textContent = this.formatDate(history[index].date, true);
            svg.appendChild(tick);
        }
    };

    Converter.prototype.drawSeries = function (svg, scale, history) {
        var gradientId = 'sidelah-cc-gradient',
            defs = svgEl('defs'),
            gradient = svgEl('linearGradient', {id: gradientId, x1: 0, y1: 0, x2: 0, y2: 1}),
            baseline = (PAD.top + scale.plotH).toFixed(2),
            line = history.map(function (point, i) {
                return (i ? 'L' : 'M') + scale.x(i).toFixed(2) + ' ' + scale.y(point.rate).toFixed(2);
            }).join(' ');

        gradient.appendChild(svgEl('stop', {offset: '0%', 'class': 'sidelah-cc__area-stop-top'}));
        gradient.appendChild(svgEl('stop', {offset: '100%', 'class': 'sidelah-cc__area-stop-bottom'}));
        defs.appendChild(gradient);
        svg.appendChild(defs);
        svg.appendChild(svgEl('path', {
            d: line + ' L' + scale.x(history.length - 1).toFixed(2) + ' ' + baseline +
                ' L' + scale.x(0).toFixed(2) + ' ' + baseline + ' Z',
            fill: 'url(#' + gradientId + ')',
            'class': 'sidelah-cc__area'
        }));
        svg.appendChild(svgEl('path', {d: line, 'class': 'sidelah-cc__line'}));
    };

    Converter.prototype.attachTooltip = function (svg, scale, history) {
        var self = this,
            container = this.els.chart,
            cursor = svgEl('line', {
                y1: PAD.top, y2: PAD.top + scale.plotH, 'class': 'sidelah-cc__cursor', visibility: 'hidden'
            }),
            dot = svgEl('circle', {r: 4.5, 'class': 'sidelah-cc__dot', visibility: 'hidden'}),
            tooltip = document.createElement('div'),
            active = -1,
            show = function (i) {
                var point, px, py, ratio;

                active = Math.max(0, Math.min(history.length - 1, i));
                point = history[active];
                px = scale.x(active);
                py = scale.y(point.rate);
                ratio = svg.getBoundingClientRect().width / scale.width || 1;
                cursor.setAttribute('x1', px);
                cursor.setAttribute('x2', px);
                cursor.setAttribute('visibility', 'visible');
                dot.setAttribute('cx', px);
                dot.setAttribute('cy', py);
                dot.setAttribute('visibility', 'visible');
                tooltip.textContent = self.formatDate(point.date) + ': ' + self.formatRate(point.rate) + ' ' +
                    self.data.to;
                tooltip.hidden = false;
                tooltip.style.left = Math.min(Math.max(px * ratio, 60), container.clientWidth - 60) + 'px';
                tooltip.style.top = py * ratio + 'px';
            },
            hide = function () {
                active = -1;
                cursor.setAttribute('visibility', 'hidden');
                dot.setAttribute('visibility', 'hidden');
                tooltip.hidden = true;
            };

        tooltip.className = 'sidelah-cc__tooltip';
        tooltip.hidden = true;
        svg.appendChild(cursor);
        svg.appendChild(dot);
        container.appendChild(tooltip);

        svg.addEventListener('pointermove', function (event) {
            var rect = svg.getBoundingClientRect(),
                localX = (event.clientX - rect.left) * (scale.width / rect.width);

            show(Math.round((localX - scale.padLeft) / scale.plotW * (history.length - 1)));
        });
        svg.addEventListener('pointerleave', hide);
        svg.addEventListener('blur', hide);
        svg.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                show((active === -1 ? history.length - 1 : active) + (event.key === 'ArrowLeft' ? -1 : 1));
            } else if (event.key === 'Escape') {
                hide();
            }
        });
    };

    return function (config, element) {
        if (!element.sidelahConverter) {
            element.sidelahConverter = new Converter(element, config);
        }
    };
});
