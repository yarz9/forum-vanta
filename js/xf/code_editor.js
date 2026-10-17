((window, document) =>
{
	'use strict'

	XF.CodeEditor = XF.Element.newHandler({
		options: {
			indentUnit: 4,
			indentWithTabs: true,
			lineNumbers: true,
			lineWrapping: false,
			autoCloseBrackets: true,
			autoCloseTags: null,
			mode: null,
			config: null,
			submitSelector: null,
			scrollbarStyle: 'simple',
		},

		editor: null,
		wrapper: null,

		init ()
		{
			if (!this.isAdvancedEditorSupported())
			{
				// textarea is hidden in the template
				XF.display(this.target)
				return
			}

			// this is checking the parent node because we have css that will force hide this textarea
			if (this.target.parentNode.scrollHeight)
			{
				this.initEditor()
			}
			else
			{
				XF.oneWithin(this.target, 'toggle:shown overlay:showing tab:shown', this.initEditor.bind(this))
			}
		},

		isAdvancedEditorSupported ()
		{
			return (XF.browser.os !== 'android')
		},

		initEditor ()
		{
			const textarea = this.target
			let lang = {}
			let config = {}

			if (textarea.dataset.cmInitialized)
			{
				return
			}

			try
			{
				config = JSON.parse(this.options.config)
			}
			catch (e)
			{
				config = this.options.config
			}

			try
			{
				lang = JSON.parse(document.querySelector('.js-codeEditorLanguage').innerHTML) || {}
			}
			catch (e)
			{
				console.error(e)
				lang = {}
			}

			config = XF.extendObject({
				mode: this.options.mode,
				indentUnit: this.options.indentUnit,
				indentWithTabs: this.options.indentWithTabs,
				lineNumbers: this.options.lineNumbers,
				lineWrapping: this.options.lineWrapping,
				autoCloseBrackets: this.options.autoCloseBrackets,
				readOnly: textarea.readOnly,
				autofocus: textarea.autofocus,
				scrollbarStyle: this.options.scrollbarStyle,
				phrases: lang,
			}, config)

			// This might come from the mode config, so only override it if something is set in the options.
			// Note that this might be overridden with a language change.
			const autoCloseTags = this.options.autoCloseTags
			if (autoCloseTags !== null)
			{
				config.autoCloseTags = autoCloseTags ? true : false
			}

			this.editor = CodeMirror.fromTextArea(textarea, config)

			this.wrapper = this.editor.getWrapperElement()

			// Sync the textarea classes to CodeMirror
			let classes = textarea.className.split(' ').filter(c => c !== '')
			classes = classes.filter(c => !c.match(/(^| )input(--[^ ]+)?(?= |$)/g))

			let wrapperClasses = this.wrapper.className.split(' ').filter(c => c !== '')
			let mergedClasses = [...new Set([...wrapperClasses, ...classes])]

			this.wrapper.className = mergedClasses.join(' ')
			textarea.className = ''

			this.wrapper.setAttribute('dir', 'ltr')

			this.editor.refresh()

			XF.layoutChange()

			this.editor.on('keydown', this.keydown.bind(this))

			const form = textarea.closest('form')
			XF.on(form, 'ajax-submit:before', this.onSubmit.bind(this))

			const initEvent = XF.customEvent('code-editor:init', {
				editor: this.editor,
			})
			XF.trigger(textarea, initEvent)

			textarea.dataset.cmInitialized = '1'
		},

		onSubmit (e)
		{
			this.editor.save()
		},

		keydown (editor, e)
		{
			// macOS: Cmd + Ctrl + F | other: F11
			if ((XF.isMac() && e.metaKey && e.ctrlKey && e.key == 'f')
				|| (!XF.isMac() && e.key == 'F11')
			)
			{
				e.preventDefault()

				editor.setOption('fullScreen', !editor.getOption('fullScreen'))
			}

			// Escape (exit full screen)
			if (e.key == 'Escape')
			{
				e.stopPropagation()

				if (editor.getOption('fullScreen'))
				{
					editor.setOption('fullScreen', false)
				}
			}

			// (ctrl|meta)+(s|enter) submits the associated form
			if ((e.key == 's' || e.key == 'Enter') && (XF.isMac() ? e.metaKey : e.ctrlKey))
			{
				e.preventDefault()

				const textarea = editor.getTextArea()
				const form = textarea.closest('form')
				const selector = this.options.submitSelector
				const submit = form.querySelector(selector)

				if (selector && submit)
				{
					submit.click()
				}
				else
				{
					if (XF.trigger(form, 'submit'))
					{
						form.submit()
					}
				}
			}
		},
	})

	XF.CodeEditorSwitcherContainer = XF.Element.newHandler({
		options: {
			switcher: '.js-codeEditorSwitcher',
			templateSuffixMode: 0,
		},

		switcher: null,

		editor: null,
		loading: false,

		init ()
		{
			XF.on(this.target, 'code-editor:init', this.initEditor.bind(this))
			XF.on(this.target, 'code-editor:reinit', this.change.bind(this))
		},

		initEditor (e)
		{
			const { editor } = e

			const switcher = this.target.querySelector(this.options.switcher)
			if (!switcher)
			{
				console.warn('Switcher container has no switcher: %o', this.target)
				return
			}
			this.switcher = switcher

			const switcherType = switcher.tagName.toLowerCase()
			const switcherTypeAttr = switcher.getAttribute('type')

			if (switcherType === 'select' || switcherTypeAttr === 'radio')
			{
				XF.on(switcher, 'change', this.change.bind(this))
			}
			else if (switcherType === 'input' && switcherTypeAttr !== 'checkbox' && switcherTypeAttr !== 'radio')
			{
				XF.on(switcher, 'blur', this.blurInput.bind(this))

				// Trigger after a short delay to get the existing template's mode and apply
				setTimeout(() => XF.trigger(switcher, 'blur'), 100)
			}
			else
			{
				console.warn('Switcher only works for text inputs, radios and selects.')
				return
			}

			this.editor = editor
		},

		change (e)
		{
			if (!this.editor)
			{
				return
			}

			let language = this.switcher.querySelector('option:checked').value

			this.switchLanguage(language)
		},

		blurInput (e)
		{
			let language = this.switcher.value

			if (this.options.templateSuffixMode)
			{
				language = language.toLowerCase()

				if (language.includes('.less'))
				{
					language = 'less'
				}
				else if (language.includes('.css'))
				{
					language = 'css'
				}
				else
				{
					language = 'html'
				}
			}

			this.switchLanguage(language)
		},

		switchLanguage (language)
		{
			if (this.loading)
			{
				return
			}

			const editor = this.editor
			const textarea = editor.getTextArea()

			editor.save()

			if (textarea.dataset.lang == language)
			{
				return
			}

			setTimeout(() =>
			{
				const isPublic = document.querySelector('html').dataset.app == 'public'
				const url = isPublic ? 'index.php?misc/code-editor-mode-loader' : 'admin.php?templates/code-editor-mode-loader'

				XF.ajax('post', XF.canonicalizeUrl(url), { language }, this.handleAjax.bind(this))
					.finally(() => { this.loading = false })
			}, 200)
		},

		handleAjax (data)
		{
			if (data.errors || data.exception)
			{
				return
			}

			if (data.redirect)
			{
				XF.redirect(data.redirect)
			}

			const editor = this.editor
			const textarea = editor.getTextArea()

			XF.setupHtmlInsert(data.html, (html, container) =>
			{
				let mode = ''

				if (data.mime)
				{
					mode = data.mime
				}
				else if (data.mode)
				{
					mode = data.mode
				}

				editor.setOption('mode', mode)
				textarea.dataset.lang = data.language
				textarea.dataset.config = JSON.stringify(data.config)
				if (data.config)
				{
					Object.entries(data.config).forEach(([key, value]) =>
					{
						editor.setOption(key, value)
					})
				}
			})
		},
	})

	XF.Element.register('code-editor', 'XF.CodeEditor')
	XF.Element.register('code-editor-switcher-container', 'XF.CodeEditorSwitcherContainer')
})(window, document)
