((window, document) =>
{
	'use strict'

	// ################################## PROFILE BANNER UPLOAD HANDLER ###########################################

	XF.BannerUpload = XF.Element.newHandler({

		options: {},

		init ()
		{
			const form = this.target
			const file = form.querySelector('.js-uploadBanner')
			const deleteButton = form.querySelector('.js-deleteBanner')

			const banner = form.querySelector('.js-banner')
			if (banner)
			{
				const container = banner.closest('.profileBannerContainer')

				if (container.classList.contains('profileBannerContainer--withBanner'))
				{
					deleteButton.classList.remove('is-hidden')
				}
				else
				{
					deleteButton.classList.add('is-hidden')
				}
			}

			XF.on(file, 'change', this.changeFile.bind(this))
			XF.on(form, 'ajax-submit:response', this.ajaxResponse.bind(this))
		},

		changeFile (e)
		{
			if (e.target.value !== '')
			{
				XF.trigger(this.target, 'submit')
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
			const deleteButton = form.querySelector('.js-deleteBanner')
			const file = form.querySelector('.js-uploadBanner')

			let container
			const banner = form.querySelector('.js-banner')
			if (banner)
			{
				container = banner.closest('.profileBannerContainer')
			}

			const banners = data.banners
			const position = data.position
			const bannerCount = Object.keys(banners).length
			const classPrefix = 'memberProfileBanner-u' + data.userId + '-'

			file.value = ''

			document.querySelectorAll('.memberProfileBanner').forEach(banner =>
			{
				const bannerParent = banner.parentNode
				const hideEmpty = banner.dataset.hideEmpty
				const toggleClass = banner.dataset.toggleClass

				let newBanner

				if (banner.matches(`[class*="${ classPrefix }"]`))
				{
					if (banner.classList.contains(classPrefix + 'm'))
					{
						newBanner = banners['m']
					}
					else if (banner.classList.contains(classPrefix + 'l'))
					{
						newBanner = banners['l']
					}
					else if (banner.classList.contains(classPrefix + 'o'))
					{
						newBanner = banners['o']
					}
				}

				banner.style.backgroundImage = newBanner ? `url(${ newBanner })` : 'none'
				banner.style.backgroundPositionY = position !== null ? `${ position }%` : null

				if (hideEmpty)
				{
					if (!newBanner)
					{
						banner.classList.add('memberProfileBanner--empty')
					}
					else
					{
						banner.classList.remove('memberProfileBanner--empty')
					}
				}

				XF.trigger(banner, 'profile-banner:refresh')

				if (toggleClass)
				{
					if (!newBanner)
					{
						bannerParent.classList.remove(toggleClass)
					}
					else
					{
						bannerParent.classList.add(toggleClass)
					}
				}
			})

			if (!bannerCount)
			{
				deleteButton.classList.add('is-hidden')
				if (container)
				{
					container.classList.remove('profileBannerContainer--withBanner')
				}
			}
			else
			{
				deleteButton.classList.remove('is-hidden')
				if (container)
				{
					container.classList.add('profileBannerContainer--withBanner')
				}
			}
		},
	})

	// ################################## BANNER POSITIONER HANDLER ###########################################

	XF.BannerPositioner = XF.Element.newHandler({

		options: {},

		banner: null,
		value: null,
		y: 0,

		ns: 'bannerPositioner',
		dragging: false,
		scaleFactor: 1,

		init ()
		{
			const banner = this.target

			this.banner = banner
			banner.style.touchAction = 'none'
			banner.style.cursor = 'move'

			this.value = banner.querySelector('.js-bannerPosY')

			this.initDragging()

			XF.on(banner, 'profile-banner:refresh', () =>
			{
				const yPos = banner.style.backgroundPositionY
				if (yPos)
				{
					this.value.value = parseFloat(yPos)
				}

				this.stopDragging()
				XF.off(banner, '.' + this.ns)
				this.initDragging()
			})
		},

		initDragging ()
		{
			const ns = this.ns
			const banner = this.banner
			let imageUrl = banner.style.backgroundImage
			const image = new Image()

			imageUrl = imageUrl.replace(/^url\(["']?(.*?)["']?\)$/i, '$1')
			if (!imageUrl || imageUrl === 'none')
			{
				return
			}

			image.onload = () =>
			{
				const setup = () =>
				{
					// scaling makes pixel-based pointer movements map to percentage shifts
					const displayScale = image.width ? banner.clientWidth / image.width : 1
					this.scaleFactor = 1 / (image.height * displayScale / 100)

					XF.on(banner, `mousedown.${ ns }`, this.dragStart.bind(this))
					XF.on(banner, `touchstart.${ ns }`, this.dragStart.bind(this))
				}

				if (banner.clientWidth > 0)
				{
					setup()
				}
				else
				{
					// it's possible for this to be triggered when the banner container has been hidden,
					// so only allow this to be triggered again once we know the banner is visible
					XF.on(banner, 'mouseover.' + ns, setup)
					XF.on(banner, 'touchstart.' + ns, setup, { passive: true })
				}
			}
			image.src = XF.canonicalizeUrl(imageUrl)
		},

		dragStart (e)
		{
			e.preventDefault()

			const ns = this.ns

			if (e.touches)
			{
				this.y = e.touches[0].clientY
			}
			else
			{
				this.y = e.clientY

				if (e.button > 0)
				{
					// probably a right click or similar
					return
				}
			}

			this.dragging = true

			XF.on(window, 'mousemove.' + ns, this.dragMove.bind(this))
			XF.on(window, 'touchmove.' + ns, this.dragMove.bind(this))

			XF.on(window, 'mouseup.' + ns, this.dragEnd.bind(this))
			XF.on(window, 'touchend.' + ns, this.dragEnd.bind(this), {
				passive: true,
			})
		},

		dragMove (e)
		{
			if (this.dragging)
			{
				e.preventDefault()

				const existingPos = parseFloat(this.banner.style.backgroundPositionY)
				let newY
				let newPos

				if (e.touches)
				{
					newY = e.touches[0].clientY
				}
				else
				{
					newY = e.clientY
				}

				newPos = existingPos + (this.y - newY) * this.scaleFactor
				newPos = Math.min(Math.max(0, newPos), 100)

				this.banner.style.backgroundPositionY = `${ newPos }%`
				this.value.value = newPos
				this.y = newY
			}
		},

		dragEnd (e)
		{
			this.stopDragging()
		},

		stopDragging ()
		{
			if (this.dragging)
			{
				XF.off(window, `.${ this.ns }`)

				this.y = 0
				this.dragging = false
			}
		},
	})

	XF.Element.register('banner-upload', 'XF.BannerUpload')
	XF.Element.register('banner-positioner', 'XF.BannerPositioner')
})(window, document)
