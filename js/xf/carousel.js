((window, document) =>
{
	'use strict'

	XF.Carousel = XF.Element.newHandler({
		options: {
			pause: 4000,
		},

		items: null,

		init ()
		{
			this.items = this.target.querySelectorAll('.carousel-container')
			this.items.forEach(item => item.classList.add('f-carousel__slide'))

			this.slider = new Carousel(
				this.target,
				{
					center: false,
					direction: XF.isRtl() ? 'rtl' : 'ltr',
					l10n: XF.CarouselL10n(),
					on: {
						ready: () =>
						{
							this.target.style.overflow = 'visible'
						},
					},
					Autoplay: {
						showProgress: false,
						timeout: this.options.pause,
					},
					Dots: true,
					Navigation: false,
				},
				{ Autoplay }
			)
		},
	})

	XF.CarouselL10n = () =>
	{
		return {
			NEXT: XF.phrase('next_slide'),
			PREV: XF.phrase('previous_slide'),
			GOTO: XF.phrase('go_to_slide_x'),
		}
	}

	XF.Element.register('carousel', 'XF.Carousel')
})(window, document)
