((window, document) =>
{
	'use strict'

	XF.Lightbox = XF.Element.newHandler({
		options: {
			lbInfobar: 1,
			lbSlideShow: 1,
			lbThumbsAuto: 1,
			lbUniversal: 0,
			lbTrigger: '.js-lbImage',
			lbContainer: '.js-lbContainer',
			lbHistory: 0,
			lbPrev: null,
			lbNext: null,
		},

		fancybox: null,
		sidebar: null,
		sidebarToggle: null,

		initialUrl: null,
		prevUrl: null,
		nextUrl: null,

		thumbsInitialized: false,

		lastIndex: null,

		isJumping: false,

		pushStateCount: 0,

		init ()
		{
			this.initContainers()

			XF.on(document, 'xf:reinit', this.checkReInit.bind(this))

			XF.onDelegated(document, 'click', 'a.js-lightboxCloser', e =>
			{
				this.getInstance().close()
			})
		},

		getInstance ()
		{
			return Fancybox.getInstance()
		},

		handlePopstate (state)
		{
			if (!this.options.lbHistory)
			{
				return
			}

			const instance = this.getInstance()
			this.pushStateCount--

			if (state && typeof state === 'object' && XF.hasOwn(state, 'slide_src'))
			{
				this.isJumping = true

				if (instance)
				{
					const index = instance.findIndexFromSrc(state.slide_src)
					if (index !== null)
					{
						instance.jumpTo(index)
					}
				}
			}
			else if (instance)
			{
				this.pushStateCount = 0 // make sure we prevent any navigation
				instance.close()
			}
		},

		initContainers ()
		{
			const containers = this.options.lbUniversal ? [this.target] : Array.from(this.target.querySelectorAll(this.options.lbContainer))
			containers.forEach(container => this._initContainer(container))
		},

		_initContainer (container)
		{
			if (XF.DataStore.get(container, 'lbInitialized'))
			{
				return
			}

			XF.DataStore.set(container, 'lbInitialized', true)

			const triggers = container.querySelectorAll(this.options.lbTrigger)
			Array.from(triggers).forEach(trigger =>
			{
				XF.trigger(trigger, 'click.xflbtrigger', this._initTrigger.bind(this))
				XF.trigger(trigger, 'mousedown.xflbtrigger', this._initTrigger.bind(this))

				XF.on(trigger, 'lightbox:image-checked', this.imageChecked.bind(this))
				this.checkImageSizes(trigger, container)
			})

			const config = this.getConfig(container)
			Fancybox.bind(`${ this.options.lbTrigger }[data-fancybox="${ this.getContainerId(container) }"]`, config)

			XF.on(container, 'lightbox:init', this.onInit.bind(this))
			XF.on(container, 'lightbox:slide-displayed', this.onSlideDisplayed.bind(this))
			XF.on(container, 'lightbox:should-close', this.onShouldClose.bind(this))
			XF.on(container, 'lightbox:closed', this.onClosed.bind(this))
		},

		_initTrigger (e)
		{
			const target = e.target.parentNode
			let open

			if (e.type === 'mousedown')
			{
				open = e.which === 2
			}
			else
			{
				open = (e.ctrlKey || e.altKey || e.metaKey || e.shiftKey)
			}

			if (open && this.isSingleImage(target))
			{
				// stop the LB from triggering
				e.stopImmediatePropagation()

				window.open(target.dataset.src, '_blank')
			}
		},

		checkReInit ({ element })
		{
			if (element === document)
			{
				return
			}

			if (!this.target.contains(element))
			{
				return
			}

			const lbTrigger = this.options.lbTrigger
			const lbContainer = this.options.lbContainer

			if (this.options.lbUniversal)
			{
				if (element.matches(lbTrigger) || element.querySelector(lbTrigger))
				{
					// reinit the one container we have
					this._reInitContainer(this.target)
				}
			}
			else if (element.matches(lbContainer) || element.querySelector(lbContainer))
			{
				// new container, reinit all to pick this one up
				this.initContainers()
			}
			else if (element.closest(lbContainer) && (element.matches(lbTrigger) || element.querySelector(lbTrigger)))
			{
				// should be an existing container but a new image
				this._reInitContainer(element.closest(lbContainer))
			}
		},

		_reInitContainer (container)
		{
			if (!XF.DataStore.get(container, 'lbInitialized'))
			{
				return
			}

			const instance = this.getInstance()
			if (instance)
			{
				instance.close()
			}

			XF.DataStore.remove(container, 'lbInitialized')

			XF.off(container, 'onThumbsShow.fb')
			XF.off(container, 'onThumbsHide.fb')
			this.thumbsInitialized = false

			XF.off(container, 'lightbox:init')
			XF.off(container, 'lightbox:activate')
			XF.off(container, 'lightbox:reveal')
			XF.off(container, 'lightbox:done')
			XF.off(container, 'lightbox:should-close')
			XF.off(container, 'lightbox:closed')

			this._initContainer(container)
		},

		initSidebar (loading)
		{
			const instance = this.getInstance()
			if (!instance)
			{
				return
			}

			const fbContainer = instance.container

			if (this.sidebar)
			{
				if (loading)
				{
					this.sidebar.classList.add('is-loading')
				}
			}
			else
			{
				this.sidebar = XF.createElementFromString(`<div class="fancybox-sidebar ${ loading ? 'is-loading' : '' }">
					<div class="fancybox-sidebar-content"></div>
					<div class="fancybox-sidebar-loader">${ XF.Icon.getIcon('default', 'spinner-third', 'fa-4x') }</div>
				</div>`)

				fbContainer.append(this.sidebar)

				const toggle = fbContainer.querySelector('.f-button[data-fancybox-sidebartoggle]')
				this.sidebarToggle = toggle

				XF.off(toggle, 'click.lbSidebar')
				XF.on(toggle, 'click.lbSidebar', this.toggleSidebar.bind(this))

				XF.on(window, 'resize.lbSidebar', this.sidebarCheckSize.bind(this))
			}

			fbContainer.classList.add('fancybox-has-sidebar')

			if (this.isSidebarEnabled())
			{
				this.sidebar.classList.add('is-active')
				fbContainer.classList.add('fancybox-show-sidebar')

				this.updateSidebarIcon(true)
			}
			else
			{
				this.updateSidebarIcon(false)
			}

			this.sidebarCheckSize()
		},

		isSidebarEnabled ()
		{
			return (
				!XF.LocalStorage.get('lbSidebarDisabled')
				&& XF.Breakpoint.isAtOrWiderThan('full')
			)
		},

		setIsSidebarEnabled (enabled)
		{
			if (enabled)
			{
				XF.LocalStorage.remove('lbSidebarDisabled')
			}
			else
			{
				XF.LocalStorage.set('lbSidebarDisabled', '1', true)
			}
		},

		toggleSidebar ()
		{
			const wasActive = this.sidebar.classList.contains('is-active')
			if (wasActive)
			{
				this.closeSidebar(false)
			}
			else
			{
				this.openSidebar(false)
			}
		},

		openSidebar (bypassStorage)
		{
			const instance = this.getInstance()
			if (!instance)
			{
				return
			}

			const fbContainer = instance.container

			this.sidebar.classList.add('is-active')
			fbContainer.classList.add('fancybox-show-sidebar')

			this.updateSidebarIcon(true)

			if (!bypassStorage)
			{
				this.setIsSidebarEnabled(true)
			}
		},

		closeSidebar (bypassStorage)
		{
			if (!this.sidebar)
			{
				return
			}

			const instance = this.getInstance()
			const fbContainer = instance.container

			this.sidebar.classList.remove('is-active')
			fbContainer.classList.remove('fancybox-show-sidebar')

			this.updateSidebarIcon(false)

			if (!bypassStorage)
			{
				this.setIsSidebarEnabled(false)
			}
		},

		updateSidebarIcon (enabled)
		{
			let icon
			if (enabled)
			{
				icon = this.sidebarToggleClose
			}
			else
			{
				icon = this.sidebarToggleOpen
			}

			const toggle = this.sidebarToggle
			const svg = toggle.querySelector('svg')
			svg.replaceWith(XF.createElementFromString(icon))
		},

		sidebarCheckSize ()
		{
			const instance = this.getInstance()
			const fbContainer = instance.container

			if (XF.Breakpoint.isAtOrNarrowerThan('medium'))
			{
				fbContainer.classList.remove('fancybox-has-sidebar')
				this.closeSidebar(true)
			}
			else
			{
				fbContainer.classList.add('fancybox-has-sidebar')
			}
		},

		initThumbs ()
		{
			const instance = this.getInstance()

			if (!instance)
			{
				return
			}

			const container = instance.container
			let scrollbarHeight

			if (!this.thumbsInitialized)
			{
				if (this.options.lbThumbsAuto)
				{
					scrollbarHeight = this.measureThumbsScrollbar()
					this.setThumbsScrollbarOffset(scrollbarHeight)
				}

				XF.off(container, 'onThumbsShow.fb')
				XF.on(container, 'onThumbsShow.fb', () =>
				{
					scrollbarHeight = this.measureThumbsScrollbar()
					this.setThumbsScrollbarOffset(scrollbarHeight)
				})

				XF.off(container, 'onThumbsHide.fb')
				XF.on(container, 'onThumbsHide.fb', () => this.setThumbsScrollbarOffset(0))

				this.thumbsInitialized = true
			}
		},

		measureThumbsScrollbar ()
		{
			const instance = this.getInstance()
			if (!instance || !instance.Thumbs || !instance.Thumbs.isActive)
			{
				return 0
			}

			return XF.measureScrollBar(instance.Thumbs.grid, 'height')
		},

		setThumbsScrollbarOffset (height)
		{
			const instance = this.getInstance()
			if (!instance || !instance.Thumbs || !instance.Thumbs.isActive)
			{
				return
			}

			instance.refs.caption.style.paddingBottom = height + 'px'
		},

		updateLastIndex ()
		{
			const instance = this.getInstance()
			let slides

			if (!instance)
			{
				return
			}

			// slides = Object.keys(instance.group)
			// this.lastIndex = parseInt(slides[slides.length - 1])
		},

		onInit ()
		{
			this.updateLastIndex()

			this.initialUrl = window.location.href

			this.prevUrl = this.options.lbPrev
			this.nextUrl = this.options.lbNext

			this.thumbsInitialized = false

			XF.Modal.open()
			XF.Lightbox.activeLb = this
		},

		onSlideDisplayed ({ instance, slide })
		{
			if (slide.type === 'ajax')
			{
				const state = {}

				const container = instance.container

				const observer = new MutationObserver((mutationsList, observer) =>
				{
					if (slide.contentEl)
					{
						const content = slide.contentEl
						const embedContent = content.querySelector('.js-embedContent')

						if (embedContent)
						{
							state[embedContent.dataset.mediaSiteId] = true
							XF.applyJsState(XF.config.jsState, state)
						}

						observer.disconnect()
					}
				})

				observer.observe(container, { childList: true, subtree: true })
			}

			XF.hideOverlays()
			XF.hideTooltips()

			const trigger = slide.triggerEl
			const fbContainer = instance.container
			let srcHref
			let sidebarHref

			if (trigger)
			{
				const fbContainer = instance.container

				if (trigger.dataset.lbSidebar || trigger.dataset.lbSidebarHref)
				{
					this.initSidebar(true)
				}
				else
				{
					fbContainer.classList.remove('fancybox-has-sidebar')
					this.closeSidebar(true)
				}

				if (trigger.dataset.lbTypeOverride)
				{
					slide.contentType = trigger.dataset.lbTypeOverride
				}

				srcHref = trigger.getAttribute('href') || slide.src
			}
			else
			{
				srcHref = slide.src
			}

			this.initThumbs()

			const toolbar = fbContainer.querySelector('.fancybox__toolbar')
			const nwTool = toolbar.querySelector('[data-fancybox-nw]')
			if (nwTool)
			{
				nwTool.setAttribute('href', srcHref)
				nwTool.setAttribute('target', '_blank')
			}

			if (this.options.lbHistory && !this.isJumping)
			{
				XF.History.push({ slide_src: slide.src }, null, srcHref)

				this.pushStateCount++
			}

			this.isJumping = false

			if (
				(trigger.dataset.lbSidebar || trigger.dataset.lbSidebarHref)
				&& fbContainer.classList.contains('fancybox-has-sidebar')
			)
			{
				if (trigger)
				{
					sidebarHref = trigger.dataset.lbSidebarHref || srcHref
				}
				else
				{
					sidebarHref = srcHref
				}

				XF.ajax(
					'get',
					sidebarHref,
					{ lightbox: true },
					data => this.sidebarLoaded(data),
					{
						skipDefault: true,
						skipError: true,
					},
				)
			}

			if (slide.index === this.lastIndex && this.nextUrl)
			{
				XF.ajax(
					'get',
					this.nextUrl,
					{ lightbox: true },
					data => this.nextLoaded(data),
					{
						skipDefault: true,
						skipError: true,
					},
				)
			}
			else if (slide.index === 0 && this.prevUrl)
			{
				XF.ajax(
					'get',
					this.prevUrl,
					{ lightbox: true },
					data => this.prevLoaded(data),
					{
						skipDefault: true,
						skipError: true,
					},
				)
			}
		},

		sidebarLoaded (data)
		{
			if (!data.html || !this.sidebar)
			{
				return
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				if (this.sidebar)
				{
					const sidebarContent = this.sidebar.querySelector('.fancybox-sidebar-content')
					sidebarContent.innerHTML = ''
					sidebarContent.append(html)
					this.sidebar.classList.remove('is-loading')
				}
			})
		},

		prevLoaded (data)
		{
			if (!data.html)
			{
				this.prevUrl = null // likely no more items to show
				return
			}

			const instance = this.getInstance()

			if (!instance)
			{
				return
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				const lbContainer = html.find(this.options.lbContainer)
				let lbTriggers = Array.from(lbContainer.querySelectorAll(this.options.lbTrigger))
				lbTriggers.reverse().forEach(trigger =>
				{
					this.updateCaption(trigger)
					instance.prependContent(trigger)
					instance.reindexSlides()

					let currIndex = instance.currIndex
					let prevIndex = instance.prevIndex

					currIndex++
					prevIndex++

					instance.currIndex = currIndex
					instance.currPos = currIndex
					instance.current.index = currIndex
					instance.current.pos = currIndex

					instance.prevIndex = prevIndex
					instance.prevPos = prevIndex
				})

				this.updateLastIndex()

				this.prevUrl = lbContainer.dataset.lbPrev

				onComplete(true)
			})
		},

		nextLoaded (data)
		{
			if (!data.html)
			{
				this.nextUrl = null // likely no more items to show
				return
			}

			const instance = this.getInstance()

			if (!instance)
			{
				return
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				const lbContainer = html.querySelector(this.options.lbContainer)
				const triggers = lbContainer.querySelectorAll(this.options.lbTrigger)

				triggers.forEach(trigger =>
				{
					this.updateCaption(trigger)
					instance.addContent(trigger)
				})

				this.updateLastIndex()

				this.nextUrl = lbContainer.dataset.lbNext

				onComplete(true)
			})
		},

		onShouldClose (e)
		{
			if (this.options.lbHistory)
			{
				if (this.pushStateCount)
				{
					window.history.replaceState(null, null, this.initialUrl)
					this.pushStateCount = 0
				}
			}

			if (this.sidebar)
			{
				this.sidebar.remove()
				this.sidebar = null
				XF.off(window, 'resize.lbSidebar')
			}
		},

		onClosed ()
		{
			XF.Modal.close()
			XF.Lightbox.activeLb = null
		},

		getContainerId (container)
		{
			return 'lb-' + container.dataset.lbId
		},

		imageChecked (e)
		{
			const {
				target,
				container,
				image,
			} = e

			let include = true

			if (image && this.isImageNaturalSize(image))
			{
				include = false
			}

			if (include)
			{
				target.dataset.fancybox = this.getContainerId(container)
				target.style.cursor = 'pointer'
				this.updateCaption(target)
			}
		},

		checkImageSizes (target, container)
		{
			const event = XF.customEvent('lightbox:image-checked', {
				container,
			})

			if (this.isSingleImage(target))
			{
				const image = target.querySelector('img[data-zoom-target="1"]')
				if (!image)
				{
					return
				}

				if (image.closest('a'))
				{
					// embedded inside a link so ignore lightbox entirely
					return
				}

				// timeout to allow animations to finish (e.g. quick edit)
				setTimeout(() =>
				{
					event.image = image

					if (!image.complete)
					{
						XF.on(image, 'load', () =>
						{
							XF.trigger(target, event)
						})
					}
					else
					{
						XF.trigger(target, event)
					}
				}, 500)
			}
			else
			{
				XF.trigger(target, event)
			}
		},

		isImageNaturalSize (image)
		{
			const dims = {
				width: image.width,
				height: image.height,
				naturalWidth: image.naturalWidth,
				naturalHeight: image.naturalHeight,
			}

			if (!dims.naturalWidth || !dims.naturalHeight)
			{
				// could be a failed image, ignore
				return true
			}

			return dims.width === dims.naturalWidth && dims.height === dims.naturalHeight
		},

		isSingleImage (target)
		{
			return target.matches('div') && target.dataset.singleImage
		},

		updateCaption (target)
		{
			if (target.dataset.caption)
			{
				return
			}

			const closestContainer = target.closest(this.options.lbContainer)

			const template = '<h4>{{title}}</h4><p><a href="{{href}}" class="js-lightboxCloser">{{desc}}</a>{{{extra_html}}}</p>'
			const image = target.querySelector('img')
			const lbId = closestContainer.dataset.lbId
			const caption = {
				title: closestContainer.dataset.lbCaptionTitle || image.getAttribute('alt') || image.getAttribute('title') || '',
				desc: target.dataset.lbCaptionDesc || closestContainer.dataset.lbCaptionDesc || '',
				href: target.dataset.lbCaptionHref || (lbId ? (window.location.href.replace(/#.*$/, '') + '#' + lbId) : null),
				extra_html: target.dataset.lbCaptionExtraHtml || '',
			}

			target.setAttribute('data-caption', Mustache.render(template, caption))
		},

		// TODO: 1. Handle newWindow button state for non images if needed

		sidebarToggleClose: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-chevrons-right"><polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline></svg>',
		sidebarToggleOpen: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-chevrons-left"><polyline points="11 17 6 12 11 7"></polyline><polyline points="18 17 13 12 18 7"></polyline></svg>',

		getConfig (container)
		{
			return {
				l10n: this.getLanguage(),
				wheel: false,
				trapFocus: false,
                placeFocusBack: false,
				Toolbar: {
					items: {
						newWindow: {
							tpl: '<button class="f-button" data-fancybox-newwindow><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-external-link"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg></button>',
							click ()
							{
								const instance = this.instance
								const slide = instance.getSlide()
								const type = slide?.type

								if (!type || type !== 'image')
								{
									return
								}

								window.open(slide.src)
							},
						},
						toggleSidebar: {
							tpl: '<div class="fancybox-sidebartoggle"><button data-fancybox-sidebartoggle class="f-button"><svg></svg></button></div>',
						},
					},
					display: {
						right: [
							'iterateZoom',
							'newWindow',
							'fullscreen',
							'slideshow',
							'download',
							'thumbs',
							'toggleSidebar',
							'close',
						],
					},
				},
				Hash: false,
				Thumbs: {
					showOnStart: this.options.lbThumbsAuto,
				},
				on: {
					init (instance)
					{
						const event = XF.customEvent('lightbox:init', {
							container,
							instance,
						})
						XF.trigger(container, event)
					},

					loaded (instance, slide)
					{
						// timeout resolves an issue with this firing too quickly for Lightbox
						setTimeout(() =>
						{
							const event = XF.customEvent('lightbox:loaded', {
								container,
								instance,
								slide,
							})

							XF.trigger(container, event)
						}, 500)
					},

					reveal (instance, slide)
					{
						const event = XF.customEvent('lightbox:reveal', {
							container,
							instance,
							slide,
						})
						XF.trigger(container, event)
					},

					'Carousel.ready Carousel.change': instance =>
					{
						const slide = instance.getSlide()
						slide.el.classList.add('fancybox__slide--video')

						const event = XF.customEvent('lightbox:slide-displayed', {
							container,
							instance,
							slide,
						})
						XF.trigger(container, event)
					},

					done (instance, slide)
					{
						const event = XF.customEvent('lightbox:done', {
							container,
							instance,
							slide,
						})
						XF.trigger(container, event)
					},

					shouldClose (instance, slide)
					{
						const event = XF.customEvent('lightbox:should-close', {
							container,
							instance,
							slide,
						})
						XF.trigger(container, event)
					},

					close (instance, slide)
					{
						const event = XF.customEvent('lightbox:closed', {
							container,
							instance,
							slide,
						})
						XF.trigger(container, event)
					},
				},
			}
		},

		getLanguage ()
		{
			return {
				CLOSE: XF.phrase('lightbox_close'),
				NEXT: XF.phrase('lightbox_next'),
				PREV: XF.phrase('lightbox_previous'),
				ERROR: XF.phrase('lightbox_error'),
				PLAY_START: XF.phrase('lightbox_start_slideshow'),
				PLAY_STOP: XF.phrase('lightbox_stop_slideshow'),
				FULL_SCREEN: XF.phrase('lightbox_full_screen'),
				THUMBS: XF.phrase('lightbox_thumbnails'),
				DOWNLOAD: XF.phrase('lightbox_download'),
				SHARE: XF.phrase('lightbox_share'),
				ZOOM: XF.phrase('lightbox_zoom'),
				NEW_WINDOW: XF.phrase('lightbox_new_window'),
				SIDEBAR_TOGGLE: XF.phrase('lightbox_toggle_sidebar'),
			}
		},
	})
	XF.Lightbox.activeLb = null

	// Allow deferred loading of this script to enable the lightbox on the container element if needed.
	XF.on(document, 'xf:reinit', e =>
	{
		const { element } = e

		if (element == document)
		{
			return
		}

		const lbContainer = element.closest('[data-xf-init~=lightbox]')
		if (!lbContainer)
		{
			return
		}

		const lbHandler = XF.Element.getHandler(lbContainer, 'lightbox')
		if (lbHandler)
		{
			// already initialized, will handle itself
			return
		}

		// setTimeout to prevent a double reinit
		setTimeout(() =>
		{
			XF.Element.initializeElement(lbContainer)
		}, 0)
	})

	XF.History.handle((state) =>
	{
		const activeLb = XF.Lightbox.activeLb
		if (activeLb)
		{
			activeLb.handlePopstate(state)
			return true
		}
	})

	XF.Element.register('lightbox', 'XF.Lightbox')
})(window, document)
