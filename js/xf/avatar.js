((window, document) =>
{
	'use strict'

	// ################################## AVATAR UPLOAD HANDLER ###########################################

	XF.AvatarUpload = XF.Element.newHandler({

		options: {},

		init ()
		{
			const form = this.target
			const file = form.querySelector('.js-uploadAvatar')
			const avatar = form.querySelector('.js-avatar')
			const deleteButton = form.querySelector('.js-deleteAvatar')

			if (avatar.querySelector('img'))
			{
				XF.display(deleteButton, 'inline-block')
			}
			else
			{
				XF.display(deleteButton, 'none')
			}

			XF.on(file, 'change', this.changeFile.bind(this))
			XF.on(form, 'ajax-submit:response', this.ajaxResponse.bind(this))
		},

		changeFile (e)
		{
			if (e.target.value != '')
			{
				XF.trigger(this.target, XF.customEvent('submit'))
			}
		},

		ajaxResponse (e)
		{
			const { data } = e

			if (data.errors || data.exception)
			{
				return
			}

			e.preventDefault()

			if (data.message)
			{
				XF.flashMessage(data.message, 3000)
			}

			const form = this.target
			const deleteBtn = form.querySelector('.js-deleteAvatar')
			const file = form.querySelector('.js-uploadAvatar')
			const avatar = form.querySelector('.js-avatar')
			const x = form.querySelector('.js-avatarX')
			const y = form.querySelector('.js-avatarY')
			const useCustom = (form.querySelector('input[name="use_custom"]:checked').value == 1)

			if (!useCustom)
			{
				document.querySelector('.js-gravatarPreview').src = data.gravatarTest ? data.gravatarPreview : data.gravatarUrl
				if (data.gravatarTest)
				{
					return
				}
			}
			else
			{
				avatar.style.left = `${ data.cropX * -1 }px`
				avatar.style.top = `${ data.cropY * -1 }px`
				x.value = data.cropX
				y.value = data.cropY

				XF.Element.initializeElement(avatar)

				file.value = ''
			}

			XF.updateAvatars(data.userId, data.avatars, useCustom)

			if (data.defaultAvatars)
			{
				XF.display(deleteBtn, 'none')
			}
			else
			{
				XF.display(deleteBtn, 'inline-block')
			}

			const cropper = document.querySelector('.js-avatarCropper')
			XF.trigger(cropper, XF.customEvent('avatar:updated', { data }))
		},
	})

	// ################################## AVATAR CROPPER HANDLER ###########################################

	XF.AvatarCropper = XF.Element.newHandler({

		options: {
			size: 96,
			x: 0,
			y: 0,
		},

		img: null,
		size: 96,

		x: 0,
		y: 0,

		imgW: null,
		imgH: null,

		cropSize: null,
		scale: null,

		init ()
		{
			XF.on(this.target, 'avatar:updated', this.avatarsUpdated.bind(this), { once: true })

			this.img = this.target.querySelector('img')

			if (!this.img)
			{
				return
			}

			this.initTest()
		},

		avatarsUpdated (e)
		{
			this.options.x = e.data.cropX
			this.options.y = e.data.cropY
			this.init()
		},

		initTest ()
		{
			const img = this.img
			let tests = 0

			const test = () =>
			{
				tests++
				if (tests > 50)
				{
					return
				}

				if (img.naturalWidth > 0)
				{
					this.setup()
				}
				else if (img.naturalWidth === 0)
				{
					setTimeout(test, 100)
				}
				// if no naturalWidth support (IE <9), don't init
			}

			test()
		},

		setup ()
		{
			this.imgW = this.img.naturalWidth
			this.imgH = this.img.naturalHeight

			this.cropSize = Math.min(this.imgW, this.imgH)
			this.scale = this.cropSize / this.options.size

			const cropbox = new XF.CropBox(this.img, {
				width: this.size,
				height: this.size,
				zoom: 0,
				maxZoom: 0,
				result: {
					cropX: this.options.x * this.scale,
					cropY: this.options.y * this.scale,
					cropW: this.cropSize,
					cropH: this.cropSize,
				},
			})

			XF.on(this.img, 'cropbox', this.onCrop.bind(this))

			// workaround for image dragging bug in Firefox
			// https://bugzilla.mozilla.org/show_bug.cgi?id=1376369
			if (XF.browser.mozilla)
			{
				XF.on(this.img, 'mousedown', e => e.preventDefault())
			}
		},

		onCrop (e)
		{
			this.target.parentNode.querySelector('.js-avatarX').value = e.results.cropX / this.scale
			this.target.parentNode.querySelector('.js-avatarY').value = e.results.cropY / this.scale
		},
	})

	XF.Element.register('avatar-upload', 'XF.AvatarUpload')
	XF.Element.register('avatar-cropper', 'XF.AvatarCropper')
})(window, document)
