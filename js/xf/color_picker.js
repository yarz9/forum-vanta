((window, document) =>
{
	'use strict'

	XF.Color = XF.create({
		hue: null,
		saturation: null,
		light: null,
		alpha: null,

		__construct ({ hue, saturation, light, alpha = 1 })
		{
			hue = Math.round(hue) % 360
			if (hue < 0)
			{
				hue += 360
			}

			this.hue = hue
			this.saturation = XF.Color.clamp(saturation)
			this.light = XF.Color.clamp(light)
			this.alpha = XF.Color.clamp(alpha * 100) / 100
		},

		set (values)
		{
			return new XF.Color({
				...this,
				...values,
			})
		},

		toCss ()
		{
			if (this.alpha !== 1.0)
			{
                return `hsla(${this.hue}, ${this.saturation}%, ${this.light}%, ${this.alpha})`;
			}

			return `hsl(${this.hue}, ${this.saturation}%, ${this.light}%)`;
		},

		toRgb ()
		{
			const hue = this.hue
			const saturation = this.saturation / 100
			const light = this.light / 100
			const alpha = this.alpha

			const f = (n) =>
			{
				const k = (n + hue / 30) % 12
				const a = saturation * Math.min(light, 1 - light)

				return light - a * Math.max(-1, Math.min(1, k - 3, 9 - k))
			}

			let [red, green, blue] = [f(0), f(8), f(4)]

			red = Math.round(red * 255)
			green = Math.round(green * 255)
			blue = Math.round(blue * 255)

			return { red, green, blue, alpha }
		},

		toHsv ()
		{
			const hue = this.hue
			let saturation = this.saturation / 100
			const light = this.light / 100
			const alpha = this.alpha

			let value = light + saturation * Math.min(light, 1 - light)
			saturation = value === 0 ? 0 : 2 * (1 - light / value)

			saturation = Math.round(saturation * 100)
			value = Math.round(value * 100)

			return { hue, saturation, value, alpha }
		},
	})

	XF.Color.palette = {}
	XF.Color.paletteParsed = {}

	XF.Color.fromValue = (value, variation = 'default') =>
	{
		value = value.trim()

		const match = value.match(/^([\w-]+)\((.+)\)$/)
		if (match)
		{
			let func = match[1]
			let args = match[2]
				.split(/(?<=\))\s*,\s*/)
				.map(arg => arg.includes(')')
					? arg
					: arg.split(',').map(a => a.trim()))
				.flat()

			if (func === 'xf-diminish' || func === 'xf-intensify')
			{
				const styleType = XF.Color.palette[variation]?.styleType
					?? 'light'
				if (styleType === 'light')
				{
					func = func === 'xf-diminish' ? 'lighten' : 'darken'
				}
				else
				{
					func = func === 'xf-diminish' ? 'darken' : 'lighten'
				}
			}

			switch (func)
			{
				case 'saturate':
				{
					const color = XF.Color.fromValue(args[0], variation)
					const amount = parseInt(args[1])
					if (!XF.Color.isValidFunctionArgument(color, amount))
					{
						return null
					}

					return color.set({ saturation: color.saturation + amount })
				}

				case 'desaturate':
				{
					const color = XF.Color.fromValue(args[0], variation)
					const amount = parseInt(args[1])
					if (!XF.Color.isValidFunctionArgument(color, amount))
					{
						return null
					}

					return color.set({ saturation: color.saturation - amount })
				}

				case 'lighten':
				{
					const color = XF.Color.fromValue(args[0], variation)
					const amount = parseInt(args[1])
					if (!XF.Color.isValidFunctionArgument(color, amount))
					{
						return null
					}

					return color.set({ light: color.light + amount })
				}

				case 'darken':
				{
					const color = XF.Color.fromValue(args[0], variation)
					const amount = parseInt(args[1])
					if (!XF.Color.isValidFunctionArgument(color, amount))
					{
						return null
					}

					return color.set({ light: color.light - amount })
				}

				case 'fadein':
				{
					const color = XF.Color.fromValue(args[0], variation)
					const amount = parseInt(args[1])
					if (!XF.Color.isValidFunctionArgument(color, amount))
					{
						return null
					}

					return color.set({ alpha: color.alpha + amount })
				}

				case 'fadeout':
				{
					const color = XF.Color.fromValue(args[0], variation)
					const amount = parseInt(args[1])
					if (!XF.Color.isValidFunctionArgument(color, amount))
					{
						return null
					}

					return color.set({ alpha: color.alpha - amount })
				}

				case 'fade':
				{
					const color = XF.Color.fromValue(args[0], variation)
					const amount = parseInt(args[1])
					if (!XF.Color.isValidFunctionArgument(color, amount))
					{
						return null
					}

					return color.set({ alpha: amount })
				}

				case 'spin':
				{
					const color = XF.Color.fromValue(args[0], variation)
					const amount = parseInt(args[1])
					if (!XF.Color.isValidFunctionArgument(color, amount))
					{
						return null
					}

					return color.set({ hue: color.hue + amount })
				}

				case 'mix':
				{
					const color1 = XF.Color.fromValue(args[0], variation)
					const color2 = XF.Color.fromValue(args[1], variation)
					const weight1 = parseInt(args[2] ?? 50) / 100
					const weight2 = 1 - weight1
					if (!XF.Color.isValidFunctionArgument(
						color1,
						color2,
						weight1,
						weight2
					))
					{
						return null
					}

					const hue = color1.hue * weight1 + color2.hue * weight2
					const saturation = color1.saturation * weight1 + color2.saturation * weight2
					const light = color1.light * weight1 + color2.light * weight2
					const alpha = color1.alpha * weight1 + color2.alpha * weight2

					return new XF.Color({ hue, saturation, light, alpha })
				}

				case 'tint':
				{
					const color = XF.Color.fromValue(args[0], variation)
					const weight = parseInt(args[1] ?? 50) / 100
					if (!XF.Color.isValidFunctionArgument(color, weight))
					{
						return null
					}

					const weight1 = weight
					const weight2 = 1 - weight

					return color.set({
						saturation: color.saturation * weight2,
						light: 100 * weight1 + color.light * weight2
					})
				}

				case 'shade':
				{
					const color = XF.Color.fromValue(args[0], variation)
					const weight = parseInt(args[1] ?? 50) / 100
					if (!XF.Color.isValidFunctionArgument(color, weight))
					{
						return null
					}

					const multiplier = 1 - weight

					return color.set({
						light: color.light * multiplier
					})
				}

				case 'greyscale':
				{
					const color = XF.Color.fromValue(args[0], variation)
					if (!XF.Color.isValidFunctionArgument(color))
					{
						return null
					}

					return color.set({ saturation: 0 })
				}

				case 'contrast':
				{
					const color = XF.Color.fromValue(args[0], variation)
					const threshold = parseInt(args[3] ?? 67)
					if (!XF.Color.isValidFunctionArgument(color, threshold))
					{
						return null
					}

					const light = (color.light - threshold) * -100
					return color?.set({ light: light }) ?? null
				}
			}
		}

		if (value.startsWith('@'))
		{
			const name = value

			if (XF.Color.paletteParsed[variation] === undefined)
			{
				XF.Color.paletteParsed[variation] = {}
			}

			if (XF.Color.paletteParsed[variation][name] === undefined)
			{
				XF.Color.paletteParsed[variation][name] = null
				const value = XF.Color.getPaletteValue(variation, name)
				const color = value
					? XF.Color.fromValue(value, variation)
					: null
				XF.Color.paletteParsed[variation][name] = color
			}

			return XF.Color.paletteParsed[variation][name]
		}

		return XF.Color.fromColor(value)
	}

	XF.Color.fromColor = (value) =>
	{
		if (!value)
		{
			return null
		}

		const hslMatch = value.match(
			/^hsl\((\d+), (\d+)%, (\d+)%(, ([\d.]+))?\)$/i
		)
		if (hslMatch)
		{
			const hue = parseInt(hslMatch[1])
			const saturation = parseInt(hslMatch[2])
			const light = parseInt(hslMatch[3])
			const alpha = hslMatch[5] ? parseFloat(hslMatch[5]) : 1

			return new XF.Color({ hue, saturation, light, alpha })
		}

		const div = document.createElement('div')
		div.style.color = value
		if (!div.style.color)
		{
			return null
		}

		document.body.append(div)
		const color = window.getComputedStyle(div).color
		div.remove()

		const rgbMatch = color.match(
			/^rgba?\((\d+),\s*(\d+),\s*(\d+)(,\s*([\d.]+))?\)$/i
		)
		if (!rgbMatch)
		{
			return null
		}

		const red = parseInt(rgbMatch[1])
		const green = parseInt(rgbMatch[2])
		const blue = parseInt(rgbMatch[3])
		const alpha = rgbMatch[5] ? parseFloat(rgbMatch[5]) : 1

		return XF.Color.fromRgb({ red, green, blue, alpha })
	}

	XF.Color.fromRgb = ({ red, green, blue, alpha = 1 }) =>
	{
		red = XF.Color.clamp(red, 0, 255)
		green = XF.Color.clamp(green, 0, 255)
		blue = XF.Color.clamp(blue, 0, 255)
		alpha = XF.Color.clamp(alpha * 100) / 100

		red /= 255
		green /= 255
		blue /= 255

		const max = Math.max(red, green, blue)
		const min = Math.min(red, green, blue)
		const chroma = max - min
		let light = (max + min) / 2

		let hue
		if (chroma === 0)
		{
			hue = 0
		}
		else
		{
			switch (max)
			{
				case red:
					hue = 60 * (((green - blue) / chroma) % 6)
					break

				case green:
					hue = 60 * (((blue - red) / chroma) + 2)
					break

				case blue:
					hue = 60 * (((red - green) / chroma) + 4)
					break
			}
		}

		let saturation = light === 0 || light === 1
			? 0
			: (max - light) / Math.min(light, 1 - light)

		saturation *= 100
		light *= 100

		return new XF.Color({ hue, saturation, light, alpha })
	}

	XF.Color.fromHsv = ({ hue, saturation, value, alpha = 1 }) =>
	{
		hue = Math.round(hue) % 360
		if (hue < 0)
		{
			hue += 360
		}

		saturation = XF.Color.clamp(saturation)
		value = XF.Color.clamp(value)
		alpha = XF.Color.clamp(alpha * 100) / 100

		saturation /= 100
		value /= 100

		let light = value * (1 - saturation / 2)
		saturation = light === 0 || light === 1
			? 0
			: (value - light) / Math.min(light, 1 - light)

		saturation *= 100
		light *= 100

		return new XF.Color({ hue, saturation, light, alpha })
	}

	XF.Color.getPaletteValue = (variation, name) =>
	{
		const palette = XF.Color.palette[variation]
		if (!palette)
		{
			return null
		}

		for (const group of Object.values(palette.groups))
		{
			for (const [colorName, color] of Object.entries(group.colors))
			{
				if (colorName !== name)
				{
					continue
				}

				return color.value
			}
		}

		return null
	}

	XF.Color.isValidFunctionArgument = (...args) =>
	{
		for (const arg of args)
		{
			if (arg instanceof XF.Color || Number.isFinite(arg))
			{
				continue
			}

			return false
		}

		return true
	}

	XF.Color.clamp = (val, min = 0, max = 100) =>
	{
		return Math.max(min, Math.min(max, Math.round(val)))
	}

	XF.MultiColor = XF.create({
		value: null,
		variation: null,
		colors: null,

		__construct (value, variation = null)
		{
			this.value = value
			this.variation = variation


			const colors = {}
			const variations = variation
				? [variation]
				: Object.keys(XF.Color.palette)
			for (const variation of variations)
			{
				colors[variation] = XF.Color.fromValue(
					value,
					variation
				)
			}

			this.colors = colors
		},

		primary (force = false)
		{
			const primary = this.variation
				? this.colors[this.variation]
				: this.colors.default

			if (!primary && force)
			{
				return XF.Color.fromValue('white')
			}

			return primary
		},

		className ()
		{
			return Object.values(this.colors).every(c => c === null)
				? 'is-unknown'
				: 'is-active'
		},

		toCss ()
		{
			const length = Object.keys(this.colors).length

			if (length === 1)
			{
				return this.primary()?.toCss() ?? 'transparent'
			}

			const backgrounds = []
			for (const [index, color] of Object.values(this.colors).entries())
			{
				const start = Math.round(index / length * 100)
				const end = Math.round((index + 1) / length * 100)
				const css = color?.toCss() ?? 'transparent'
				backgrounds.push(`${css} ${start}%`)
				backgrounds.push(`${css} ${end}%`)
			}

			return `linear-gradient(to bottom right, ${backgrounds.join(', ')})`
		},
	})

	XF.ColorPicker = XF.Element.newHandler({
		options: {
			input: '| .input',
			box: '| .js-colorPickerTrigger',
			allowPalette: true,
			paletteVariation: null,
			paletteName: null,
		},

		input: null,
		box: null,
		hslTxtEls: null,

		menu: null,
		menuEls: {},

		inputColors: null,
		menuColors: null,
		allowReparse: true,

		init ()
		{
			this.input = XF.findRelativeIf(this.options.input, this.target)
			this.box = XF.findRelativeIf(this.options.box, this.target)
			this.hslTxtEls = {
				h: this.target.querySelector('.js-hslTxt-h'),
				s: this.target.querySelector('.js-hslTxt-s'),
				l: this.target.querySelector('.js-hslTxt-l'),
			}

			this.box.append(XF.createElementFromString(
				'<span class="color-sample"></span>'
			))

			XF.on(document, 'color-picker:reparse', this.reparse.bind(this))
			XF.on(this.input, 'input', this.inputChange.bind(this))
			XF.on(this.box, 'click', this.boxClick.bind(this))

			this.updateInputColor()
		},

		reparse ()
		{
			if (!this.allowReparse)
			{
				return
			}

			this.updateInputColor()
			this.destroyMenu()
		},

		inputChange ()
		{
			this.updateInputColor(true)
		},

		boxClick (e)
		{
			if (this.input.disabled)
			{
				this.menu?.close()
				return
			}

			this.createMenu()

			if (!this.menu.isOpen())
			{
				this.onMenuOpen()
			}

			this.menu.click(e)
		},

		createMenu ()
		{
			if (this.menu)
			{
				return
			}

			const menu = this.getMenuElement()
			this.box.after(menu)
			XF.activate(menu)

			this.menu = new XF.MenuClick(this.box, {})

			this.menuEls = {
				palette: menu.querySelectorAll('.colorPicker-palette'),

				gradientGrid: menu.querySelector(
					'.colorPicker-sliders-gradient-grid'
				),
				gradientIndicator: menu.querySelector(
					'.colorPicker-sliders-gradient-indicator'
				),

				hueBar: menu.querySelector('.colorPicker-sliders-hue-bar'),
				hueIndicator: menu.querySelector(
					'.colorPicker-sliders-hue-indicator'
				),

				alphaBar: menu.querySelector('.colorPicker-sliders-alpha-bar'),
				alphaIndicator: menu.querySelector(
					'.colorPicker-sliders-alpha-indicator'
				),

				previewOriginal: menu.querySelector(
					'.colorPicker-preview-original'
				),
				previewCurrent: menu.querySelector(
					'.colorPicker-preview-current'
				),

				input: menu.querySelector('.colorPicker-input'),
				reset: menu.querySelector('.colorPicker-reset'),
				save: menu.querySelector('.colorPicker-save'),
			}

			this.menuEls.palette.forEach(p =>
			{
				XF.onDelegated(
					p,
					'click',
					'.colorPicker-palette-color',
					this.paletteClick.bind(this)
				)
			})

			XF.on(
				this.menuEls.gradientGrid,
				'mousedown',
				this.gradientGridMouseDown.bind(this)
			)
			XF.on(
				this.menuEls.hueBar,
				'mousedown',
				this.hueBarMouseDown.bind(this)
			)
			XF.on(
				this.menuEls.alphaBar,
				'mousedown',
				this.alphaBarMouseDown.bind(this)
			)

			XF.on(this.menuEls.input, 'input', this.menuInputChange.bind(this))
			XF.on(this.menuEls.reset, 'click', this.menuResetClick.bind(this))
			XF.on(this.menuEls.save, 'click', this.menuSaveClick.bind(this))
		},

		getMenuElement ()
		{
			const palette = this.getMenuPalette()

			const template = document.querySelector('#xfColorPickerMenuTemplate').innerHTML
			const params = {
				palette,
			}
			const view = Mustache.render(template, params).trim()

			return XF.createElementFromString(view)
		},

		getMenuPalette ()
		{
			if (!this.options.allowPalette)
			{
				return []
			}

			const palette = []

			const groups = XF.Color.palette.default.groups
			for (const [name, group] of Object.entries(groups))
			{
				const colors = []

				for (const [name, color] of Object.entries(group.colors))
				{
					if (
						this.options.paletteName &&
						this.options.paletteName === name
					)
					{
						continue
					}

					const c = new XF.MultiColor(
						name,
						this.options.paletteVariation
					)
					const className = c.className()
					const background = c.toCss()

					colors.push({
						name: name,
						title: color.title,
						className,
						background,
					})
				}

				palette.push({
					name: name,
					title: group.title,
					colors,
				})
			}

			return palette
		},

		onMenuOpen ()
		{
			this.menuEls.input.value = this.input.value
			this.updateMenuColor()

			this.menuEls.previewOriginal.style.background = this.inputColors.toCss()
		},

		destroyMenu ()
		{
			if (!this.menu)
			{
				return
			}

			this.menu?.close()
			this.menu = null
			this.menuEls = {}
			this.menuColors = null
		},

		paletteClick (e)
		{
			e.preventDefault()

			const color = e.target.closest('.colorPicker-palette-color')
			this.menuEls.input.value = color.dataset.name
			this.updateMenuColor()
		},

		gradientGridMouseDown (e)
		{
			XF.MenuWatcher.preventDocClick()
			document.body.style.cursor = 'all-scroll'
			XF.on(
				document,
				'mousemove.colorpicker',
				e => this.gradientGridMouseAction(e)
			)

			this.gradientGridMouseAction(e)

			XF.on(document, 'mouseup.colorpicker', () =>
			{
				XF.off(document, '.colorpicker')
				document.body.style.cursor = ''
				setTimeout(XF.MenuWatcher.allowDocClick, 0)
			})
		},

		gradientGridMouseAction (e)
		{
			e.preventDefault()

			const rect = this.menuEls.gradientGrid.getBoundingClientRect()
			const percentX = XF.Color.clamp(
				100 * (e.clientX - rect.left) / this.menuEls.gradientGrid.offsetWidth
			)
			const percentY = XF.Color.clamp(
				100 * (e.clientY - rect.top) / this.menuEls.gradientGrid.offsetHeight
			)
			const saturation = percentX
			const value = 100 - percentY

			this.menuEls.input.value = XF.Color
				.fromHsv({
					...this.menuColors.primary(true).toHsv(),
					saturation,
					value,
				})
				.toCss()
			this.updateMenuColor()
		},

		hueBarMouseDown (e)
		{
			XF.MenuWatcher.preventDocClick()
			document.body.style.cursor = 'ew-resize'
			XF.on(
				document,
				'mousemove.colorpicker',
				e => this.hueBarMouseAction(e)
			)

			this.hueBarMouseAction(e)

			XF.on(document, 'mouseup.colorpicker', () =>
			{
				XF.off(document, '.colorpicker')
				document.body.style.cursor = ''
				setTimeout(XF.MenuWatcher.allowDocClick, 0)
			})
		},

		hueBarMouseAction (e)
		{
			e.preventDefault()

			const rect = this.menuEls.hueBar.getBoundingClientRect()
			const percent = XF.Color.clamp(
				100 * (e.clientX - rect.left) / this.menuEls.hueBar.offsetWidth
			)
			const hue = 359 - 359 * percent / 100

			this.menuEls.input.value = this.menuColors.primary(true)
				.set({ hue })
				.toCss()
			this.updateMenuColor()
		},

		alphaBarMouseDown (e)
		{
			XF.MenuWatcher.preventDocClick()
			document.body.style.cursor = 'ew-resize'
			XF.on(
				document,
				'mousemove.colorpicker',
				e => this.alphaBarMouseAction(e)
			)

			this.alphaBarMouseAction(e)

			XF.on(document, 'mouseup.colorpicker', () =>
			{
				XF.off(document, '.colorpicker')
				document.body.style.cursor = ''
				setTimeout(XF.MenuWatcher.allowDocClick, 0)
			})
		},

		alphaBarMouseAction (e)
		{
			e.preventDefault()

			const rect = this.menuEls.alphaBar.getBoundingClientRect()
			const percent = XF.Color.clamp(
				100 * (e.clientX - rect.left) / this.menuEls.alphaBar.offsetWidth
			)
			const alpha = percent / 100

			this.menuEls.input.value = this.menuColors.primary(true)
				.set({ alpha })
				.toCss()
			this.updateMenuColor()
		},

		menuInputChange ()
		{
			this.updateMenuColor()
		},

		menuResetClick ()
		{
			this.menuEls.input.value = this.input.value
			this.updateMenuColor()
		},

		menuSaveClick ()
		{
			this.menu?.close()
			this.input.value = this.menuEls.input.value
			this.updateInputColor(true)
		},

		updateMenuColor ()
		{
			this.menuColors = new XF.MultiColor(
				this.menuEls.input.value,
				this.options.paletteVariation
			)

			this.updatePaletteSelections()
			this.updatePickerSelections()
			this.menuEls.previewCurrent.style.background = this.menuColors.toCss()
		},

		updatePaletteSelections ()
		{
			for (const container of this.menuEls.palette)
			{
				for (const color of container.querySelectorAll('.colorPicker-palette-color'))
				{
					if (this.menuEls.input.value.trim() === color.dataset.name)
					{
						color.classList.add('is-active')
					}
					else
					{
						color.classList.remove('is-active')
					}
				}
			}
		},

		updatePickerSelections ()
		{
			const primary = this.menuColors.primary(true)
			const transparentColor = primary.set({ alpha: 0 })
			const opaqueColor = primary.set({ alpha: 1 })
			const hsvColor = primary.toHsv()
			const gridColor = opaqueColor.set({
				saturation: 100,
				light: 50,
			})

			const gridX = hsvColor.saturation + '%'
			const gridY = (100 - hsvColor.value) + '%'
			this.menuEls.gradientGrid.style.background = gridColor.toCss()
			this.menuEls.gradientIndicator.style.left = gridX
			this.menuEls.gradientIndicator.style.top = gridY
			this.menuEls.gradientIndicator.style.background = opaqueColor.toCss()

			const hueX = (359 - primary.hue) / 359 * 100 + '%'
			this.menuEls.hueIndicator.style.left = hueX

			const alphaX = primary.alpha * 100 + '%'
			this.menuEls.alphaIndicator.style.left = alphaX
			this.menuEls.alphaBar.style.background = `linear-gradient(
				to right,
				${transparentColor.toCss()},
				${opaqueColor.toCss()})`
		},

		updateInputColor (updatePalette = false)
		{
			this.inputColors = new XF.MultiColor(
				this.input.value,
				this.options.paletteVariation
			)
			this.updateBox()
			this.updateHslTxt()

			if (updatePalette)
			{
				this.updatePalette(this.input.value)
			}
		},

		updateBox ()
		{
			this.box.classList.remove('is-active', 'is-unknown')

			if (this.input.value)
			{
				this.box.classList.add(this.inputColors.className())
			}

			const sample = this.box.querySelector('.color-sample')
			sample.style.background = this.inputColors.toCss()
		},

		updateHslTxt ()
		{
			if (!this.hslTxtEls.h)
			{
				return
			}

			const primary = this.inputColors.primary()
			if (!primary)
			{
				this.hslTxtEls.h.textContent = ''
				this.hslTxtEls.s.textContent = ''
				this.hslTxtEls.l.textContent = ''
				return
			}

			this.hslTxtEls.h.textContent = primary.hue
			this.hslTxtEls.s.textContent = primary.saturation + '%'
			this.hslTxtEls.l.textContent = primary.light + '%'
		},

		updatePalette (value)
		{
			const variation = this.options.paletteVariation
			const name = this.options.paletteName
			if (!variation || !name)
			{
				return
			}

			if (!XF.Color.palette[variation])
			{
				return
			}

			for (const group of Object.keys(XF.Color.palette[variation]['groups']))
			{
				if (XF.Color.palette[variation]['groups'][group]['colors'][name] !== undefined)
				{
					XF.Color.palette[variation]['groups'][group]['colors'][name]['value'] = value
				}
			}

			this.allowReparse = false
			XF.Color.paletteParsed = {}
			XF.trigger(document, 'color-picker:reparse')
			this.destroyMenu()
			this.allowReparse = true
		},
	})

	XF.Element.register('color-picker', 'XF.ColorPicker')

	XF.ready(() =>
	{
		const colorPickerData = document.querySelector('.js-colorPickerData')?.innerHTML
		if (!colorPickerData)
		{
			return
		}

		const palette = JSON.parse(colorPickerData)

		if (palette)
		{
			XF.Color.palette = {
				...XF.Color.palette,
				...palette,
			}
			XF.Color.paletteParsed = {}
			XF.trigger(document, 'color-picker:reparse')
		}
	})
})(window, document)
