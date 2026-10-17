((window, document) =>
{
	XF.CropBox = XF.create({
		options: {},

		target: null,
		frame: null,

		width: null,
		height: null,

		imageWidth: null,
		imageHeight: null,
		imageLeft: 0,
		imageTop: 0,

		percent: 0,
		percentMin: null,

		onload: null,

		__construct (target, options, onload)
		{
			this.target = target
			this.options = XF.extendObject(true, {}, this.options, options)

			this.target.draggable = false
			this.target.classList.add('cropImage')

			this.frame = XF.createElement('div', {
				className: 'cropFrame'
			})
			target.parentNode.insertBefore(this.frame, target)
			this.frame.appendChild(target)

			this.onload = onload || (() => {})

			this.init()
		},

		init ()
		{
			this.updateOptions()

			let dragData = null

			XF.on(this.target, 'pointerdown.cropbox', e1 =>
			{
				if (dragData)
				{
					// If an operation is ongoing, stop it
					return
				}

				dragData = {
					initialX: e1.pageX,
					initialY: e1.pageY,
					startX: this.imageLeft,
					startY: this.imageTop,
					dx: 0,
					dy: 0,
				}

				e1.preventDefault()
			})

			XF.on(document, 'pointermove.cropbox', e2 =>
			{
				if (!dragData)
				{
					// If drag hasn't started, don't proceed
					return
				}

				dragData.dx = e2.pageX - dragData.initialX
				dragData.dy = e2.pageY - dragData.initialY

				this.drag({
					startX: dragData.startX,
					startY: dragData.startY,
					dx: dragData.dx,
					dy: dragData.dy,
				}, true)
			})

			XF.on(document, 'pointerup.cropbox', () =>
			{
				if (!dragData)
				{
					return
				}

				this.update()

				// Clear the drag data
				dragData = null
			})

			XF.on(this.target, 'wheel.cropbox', e =>
			{
				e.preventDefault()
				if (e.deltaY < 0)
				{
					this.zoomIn()
				}
				else
				{
					this.zoomOut()
				}
			})
		},

		updateOptions ()
		{
			this.imageTop = 0
			this.imageLeft = 0

			this.target.style.width = ''
			this.target.left = this.imageLeft + 'px'
			this.target.top = this.imageTop + 'px'

			this.frame.style.width = this.options.width + 'px'
			this.frame.style.height = this.options.height + 'px'

			XF.off(this.frame, '.cropbox')

			this.frame.classList.remove('hover')

			const img = new Image()
			img.onload = () =>
			{
				this.width = img.width
				this.height = img.height
				img.src = ''
				img.onload = null
				this.percent = 0
				this.fit()
				if (this.options.result)
				{
					this.setCrop(this.options.result)
				}
				else
				{
					this.zoom(this.minPercent)
				}
				XF.Animate.fadeIn(this.target, { speed: 500 })
				this.onload(this)
			}
			img.src = this.target.getAttribute('src')
		},

		remove ()
		{
			XF.off(this.frame, '.cropbox')
			XF.off(this.target, '.cropbox')

			this.target.style.width = ''
			this.target.style.left = ''
			this.target.style.top = ''

			this.target.classList.remove('cropImage')
			this.target.removeAttribute('data-cropbox')
			this.frame.after(this.target)
			this.frame.classList.remove('cropFrame')
			this.frame.removeAttribute('style')
			while (this.frame.firstChild)
			{
				this.frame.removeChild(this.frame.firstChild)
			}
			this.frame.parentNode.removeChild(this.frame)
		},

		fit ()
		{
			const widthRatio = this.options.width / this.width,
				heightRatio = this.options.height / this.height
			this.minPercent = (widthRatio >= heightRatio) ? widthRatio : heightRatio
		},

		setCrop (result)
		{
			this.percent = Math.max(this.options.width / result.cropW, this.options.height / result.cropH)
			this.imageWidth = Math.ceil(this.width * this.percent)
			this.imageHeight = Math.ceil(this.height * this.percent)
			this.imageLeft = -Math.floor(result.cropX * this.percent)
			this.imageTop = -Math.floor(result.cropY * this.percent)

			this.target.style.width = this.imageWidth + 'px'
			this.target.style.left = this.imageLeft + 'px'
			this.target.style.top = this.imageTop + 'px'

			this.update()
		},

		zoom (percent)
		{
			const oldPercent = this.percent

			this.percent = Math.max(this.minPercent, Math.min(this.options.maxZoom, percent))
			this.imageWidth = Math.ceil(this.width * this.percent)
			this.imageHeight = Math.ceil(this.height * this.percent)

			if (oldPercent)
			{
				const zoomFactor = this.percent / oldPercent
				this.imageLeft = this.fill((1 - zoomFactor) * this.options.width / 2 + zoomFactor * this.imageLeft, this.imageWidth, this.options.width)
				this.imageTop = this.fill((1 - zoomFactor) * this.options.height / 2 + zoomFactor * this.imageTop, this.imageHeight, this.options.height)
			}
			else
			{
				this.imageLeft = this.fill((this.options.width - this.imageWidth) / 2, this.imageWidth, this.options.width)
				this.imageTop = this.fill((this.options.height - this.imageHeight) / 2, this.imageHeight, this.options.height)
			}

			this.target.style.width = this.imageWidth + 'px'
			this.target.style.left = this.imageLeft + 'px'
			this.target.style.top = this.imageTop + 'px'

			this.update()
		},

		zoomIn ()
		{
			this.zoom(this.percent + (1 - this.minPercent) / (this.options.zoom - 1 || 1))
		},

		zoomOut ()
		{
			this.zoom(this.percent - (1 - this.minPercent) / (this.options.zoom - 1 || 1))
		},

		drag (data, skipupdate)
		{
			this.imageLeft = this.fill(data.startX + data.dx, this.imageWidth, this.options.width)
			this.imageTop = this.fill(data.startY + data.dy, this.imageHeight, this.options.height)

			this.target.style.left = this.imageLeft + 'px'
			this.target.style.top = this.imageTop + 'px'

			if (!skipupdate)
			{
				this.update()
			}
		},

		update ()
		{
			this.result = {
				cropX: -Math.ceil(this.imageLeft / this.percent),
				cropY: -Math.ceil(this.imageTop / this.percent),
				cropW: Math.floor(this.options.width / this.percent),
				cropH: Math.floor(this.options.height / this.percent),
				stretch: this.minPercent > 1,
			}

			XF.trigger(this.target, XF.customEvent('cropbox', {
				results: this.result,
				cropbox: this,
			}))
		},

		getDataURL ()
		{
			const canvas = document.createElement('canvas'), ctx = canvas.getContext('2d')
			canvas.width = this.options.width
			canvas.height = this.options.height
			ctx.drawImage(this.target, this.result.cropX, this.result.cropY, this.result.cropW, this.result.cropH, 0, 0, this.options.width, this.options.height)
			return canvas.toDataURL()
		},

		getBlob ()
		{
			return this.uri2Blob(this.getDataURL())
		},

		uri2Blob (dataUri)
		{
			const uriComponents = dataUri.split(',')
			const byteString = atob(uriComponents[1])
			const mimeString = uriComponents[0].split(':')[1].split(';')[0]
			const ab = new ArrayBuffer(byteString.length)
			const ia = new Uint8Array(ab)

			for (let i = 0; i < byteString.length; i++)
			{
				ia[i] = byteString.charCodeAt(i)
			}
			return new Blob([ab], { type: mimeString })
		},

		fill (value, target, container)
		{
			if (value + target < container)
			{
				value = container - target
			}
			return value > 0 ? 0 : value
		},
	})
})(window, document)
