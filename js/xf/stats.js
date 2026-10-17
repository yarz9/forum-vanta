((window, document) =>
{
	'use strict'

	XF.Stats = XF.Element.newHandler({
		options: {
			data: '| .js-statsData',
			seriesLabels: '| .js-statsSeriesLabels',
			legend: '| .js-statsLegend',
			chart: '| .js-statsChart',

			maxTicks: 9,
			lineSmooth: false,
			showArea: false,
			showPoint: true,
			averages: true,
		},

		chartEl: null,
		chart: null,
		seriesLabels: {},
		labelMap: {},
		tooltipEl: null,

		init ()
		{
			this.chartEl = XF.findRelativeIf(this.options.chart, this.target)

			let data = {}
			const dataEl = XF.findRelativeIf(this.options.data, this.target)
			let seriesLabels = {}
			const seriesLabelsEl = XF.findRelativeIf(this.options.seriesLabels, this.target)

			try
			{
				data = JSON.parse(dataEl.innerHTML) || {}
			}
			catch (e)
			{
				console.error('Stats data not valid: ', e)
				return
			}

			try
			{
				seriesLabels = JSON.parse(seriesLabelsEl.innerHTML) || {}
			}
			catch (e)
			{
				console.error('Series labels not valid: ', e)
			}

			this.seriesLabels = seriesLabels

			const chartData = this.setupChartData(data)
			const chartOptions = this.setupChartOptions(chartData)
			const chartResponsive = this.setupChartResponsive(chartData, chartOptions)

			this.createChart(chartData, chartOptions, chartResponsive)
		},

		setupChartData (data)
		{
			const labels = []
			const labelMap = {}
			let series = null
			let point = 0

			Object.entries(data).forEach(([k, v]) =>
			{
				let i = 0
				const valueType = this.options.averages ? 'averages' : 'values'
				const tipType = `${ valueType }.tips`
				const chartValues = v[valueType]

				labels.push(point)
				labelMap[point] = v.label

				if (series === null)
				{
					series = []
					for (const seriesType of Object.keys(chartValues))
					{
						series.push({
							name: this.seriesLabels[seriesType],
							data: [],
						})
					}
				}

				for (const type of Object.keys(chartValues))
				{
					let item = {
						x: point,
						y: chartValues[type],
					}

					if (XF.hasOwn(v, tipType))
					{
						item.tip = v[tipType][type]
					}

					series[i].data.push(item)
					i++
				}

				point++
			})

			this.labelMap = labelMap

			return {
				labels,
				series,
			}
		},

		setupChartOptions (chartData)
		{
			const labels = chartData.labels

			return {
				fullWidth: true,
				lineSmooth: this.options.lineSmooth,
				showArea: this.options.showArea,
				showPoint: this.options.showPoint,
				axisY: {
					onlyInteger: true,
					labelOffset: {
						x: 0,
						y: 6,
					},
				},
				axisX: {
					type: Chartist.FixedScaleAxis,
					ticks: this.getTicks(labels, this.options.maxTicks),
					low: labels[0],
					high: labels.length ? labels[labels.length - 1] : 0,
					labelOffset: {
						x: 0,
						y: 4,
					},
					labelInterpolationFnc: (value) =>
					{
						if (value >= labels[labels.length - 1])
						{
							// there isn't enough space to plot the last point
							return '\u00A0'
						}

						return this.labelMap[value]
					},
				},
			}
		},

		getTicks (labels, maxTicks)
		{
			const ticks = []
			const tickEvery = Math.ceil(labels.length / maxTicks)

			for (let i = 0; i < labels.length; i++)
			{
				if (i % tickEvery == 0)
				{
					ticks.push(labels[i])
				}
			}

			return ticks
		},

		setupChartResponsive (data, options)
		{
			return [
				['screen and (max-width: 800px)', {
					axisX: {
						ticks: this.getTicks(data.labels, Math.min(6, this.options.maxTicks)),
					},
				}],
				['screen and (max-width: 500px)', {
					axisX: {
						ticks: this.getTicks(data.labels, Math.min(3, this.options.maxTicks)),
					},
				}],
			]
		},

		createChart (data, options, responsive)
		{
			this.chart = new Chartist.Line(this.chartEl, data, options, responsive)

			this.tooltipEl = new XF.TooltipElement(document.createElement('span'), {
				html: true,
			})

			XF.onDelegated(this.chartEl, 'mouseover', '.ct-point', this.showTooltip.bind(this, data))
			XF.onDelegated(this.chartEl, 'focusin', '.ct-point', this.showTooltip.bind(this, data))

			XF.onDelegated(this.chartEl, 'mouseout', '.ct-point', this.hideTooltip.bind(this))
			XF.onDelegated(this.chartEl, 'focusout', '.ct-point', this.hideTooltip.bind(this))

			const legend = XF.findRelativeIf(this.options.legend, this.target)
			const chartEl = this.chartEl
			const chart = this.chart

			if (legend)
			{
				setTimeout(() =>
				{
					chart.data.series.forEach((series, k) =>
					{
						const className = series.className || `${ chart.options.classNames.series }-${ Chartist.alphaNumerate(k) }`
						const el = chartEl.querySelector(`.${ className }`).querySelector('.ct-line, .ct-point')

						if (el)
						{
							const li = XF.createElement('li', {
								textContent: series.name
							})

							const stroke = window.getComputedStyle(el).getPropertyValue('stroke')
							const i = XF.createElement('i', {
								style: { background: stroke }
							})

							li.prepend(i)
							legend.append(li)
						}
					})
				}, 0)
			}
		},

		showTooltip (data, e)
		{
			const point = e.target
			const series = point.closest('.ct-series')
			const allSeries = Array.from(document.querySelectorAll('.ct-series'))
			const seriesIndex = allSeries.indexOf(series)
			const ctValue = point.getAttribute('ct:value').split(',')
			const axisLabel = ctValue[0]
			const value = ctValue[1] || 0

			if (data.series[seriesIndex] && data.series[seriesIndex].data[axisLabel] && XF.hasOwn(data.series[seriesIndex].data[axisLabel], 'tip'))
			{
				this.tooltipEl.content.innerHTML = data.series[seriesIndex].data[axisLabel].tip
			}
			else
			{
				this.tooltipEl.content.textContent = `${ series.getAttribute('ct:series-name') || '' } - ${ this.labelMap[axisLabel] }: ${ value }`
			}

			this.tooltipEl.setPositioner(point)
			this.tooltipEl.show()
		},

		hideTooltip (e)
		{
			this.tooltipEl.hide()
		},
	})

	XF.Element.register('stats', 'XF.Stats')
})(window, document)
