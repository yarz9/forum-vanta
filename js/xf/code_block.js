((window, document) =>
{
	'use strict'

	XF.CodeBlock = XF.Element.newHandler({
		options: {
			lang: null,
		},

		init ()
		{
			const language = this.options.lang
			const code = this.target.querySelector('code')

			if (!language || typeof Prism != 'object' || !code)
			{
				return
			}

			code.classList.add(`language-${ language }`)

			Prism.plugins.customClass.map({})
			Prism.plugins.customClass.prefix('prism-')

			Prism.highlightElement(code)
		},
	})

	XF.Element.register('code-block', 'XF.CodeBlock')
})(window, document)
