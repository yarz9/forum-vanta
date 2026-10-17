/* eslint-disable @typescript-eslint/no-this-alias */
((window, document) =>
{
	'use strict'

	XF.FE = FroalaEditor

	XF.isEditorEnabled = () => XF.LocalStorage.get('editorDisabled') ? false : true
	XF.setIsEditorEnabled = enabled =>
	{
		if (enabled)
		{
			XF.LocalStorage.remove('editorDisabled')
		}
		else
		{
			XF.LocalStorage.set('editorDisabled', '1', true)
		}
	}

	XF.Editor = XF.Element.newHandler({
		options: {
			maxHeight: 0.70,
			minHeight: 250, // default set in Templater->formEditor() $controlOptions['data-min-height']
			buttonsRemove: '',
			attachmentTarget: true,
			deferred: false,
			attachmentUploader: '.js-attachmentUpload',
			attachmentContextInput: 'attachment_hash_combined',
		},

		edMinHeight: 63, // Froala seems to force height to a minimum of 63

		form: null,
		buttonManager: null,
		ed: null,
		mentioner: null,
		emojiCompleter: null,
		uploadUrl: null,

		init ()
		{
			if (!this.target.matches('textarea'))
			{
				console.error('Editor can only be initialized on a textarea')
				return
			}

			// make sure the min height cannot be below the minimum
			this.options.minHeight = Math.max(this.edMinHeight, this.options.minHeight)

			XF.trigger(document, XF.customEvent('editor:start', {
				editor: this,
			}))

			this.form = this.target.closest('form')
			if (!this.form)
			{
				this.form = null
			}

			if (this.options.attachmentTarget)
			{
				const attachManager = this.target.closest('[data-xf-init~=attachment-manager]')
				const uploader = attachManager?.querySelector(this.options.attachmentUploader)
				this.uploadUrl = uploader?.getAttribute('href')
			}

			if (!this.options.deferred)
			{
				this.startInit()
			}
		},

		startInit (callbacks)
		{
			const cbBefore = callbacks && callbacks.beforeInit
			const cbAfter = callbacks && callbacks.afterInit

			this.target.style.visibility = ''

			this.ed = new FroalaEditor(this.target, this.getEditorConfig(), () =>
			{
				if (cbBefore)
				{
					cbBefore(this, this.ed)
				}

				this.editorInit()

				if (cbAfter)
				{
					cbAfter(this, this.ed)
				}
			})
		},

		reInit (callbacks)
		{
			if (this.ed)
			{
				this.ed.destroy()

				this.startInit(callbacks)
			}
		},

		getEditorConfig ()
		{
			const fontSize = ['9', '10', '12', '15', '18', '22', '26']
			const fontFamily = {
				'arial': 'Arial',
				'\'book antiqua\'': 'Book Antiqua',
				'\'courier new\'': 'Courier New',
				'georgia': 'Georgia',
				'tahoma': 'Tahoma',
				'\'times new roman\'': 'Times New Roman',
				'\'trebuchet ms\'': 'Trebuchet MS',
				'verdana': 'Verdana',
			}

			const heightLimits = this.getHeightLimits()

			let config = {
				attribution: false,
				direction: FroalaEditor.LANGUAGE.xf.direction,
				editorClass: 'bbWrapper', // since this is a BB code editor, we want our output to normalize like BB code
				fileUpload: false,
				fileMaxSize: 4 * 1024 * 1024 * 1024, // 4G
				fileUploadParam: 'upload',
				fileUploadURL: false,
				fontFamily,
				fontSize,
				heightMin: heightLimits[0],
				heightMax: heightLimits[1],
				htmlAllowedTags: ['a', 'audio', 'b', 'bdi', 'bdo', 'blockquote', 'br', 'cite', 'code', 'dfn', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'mark', 'ol', 'p', 'pre', 's', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'time', 'tr', 'u', 'ul', 'var', 'video', 'wbr'],
				key: 'ZOD3gA8B10A6C5A2G3C-8TMIBDIa1NTMNZFFPFZc1d1Ib2a1E1fA4A3G3F3F2B6C4C4C3G3==',
				htmlAllowComments: false,
				iconsTemplate: 'fa_svg',
				imageUpload: false,
				imageCORSProxy: null,
				imageDefaultDisplay: 'inline',
				imageDefaultWidth: 0,
				imageEditButtons: ['imageAlign', 'imageSize', 'imageAlt', '|', 'imageReplace', 'imageRemove', '|', 'imageLink', 'linkOpen', 'linkEdit', 'linkRemove'],
				imageManagerLoadURL: false,
				imageMaxSize: 4 * 1024 * 1024 * 1024, // 4G
				imagePaste: false,
				imageResize: true,
				imageUploadParam: 'upload',
				imageUploadRemoteUrls: false,
				imageUploadURL: false,
				language: 'xf',
				linkAlwaysBlank: true,
				linkEditButtons: ['linkOpen', 'linkEdit', 'linkRemove'],
				linkInsertButtons: ['linkBack'],
				listAdvancedTypes: false,
				paragraphFormat: {
					N: 'Normal',
					H2: 'Heading 1',
					H3: 'Heading 2',
					H4: 'Heading 3',
				},
				placeholderText: '',
				tableResizer: true,
				tableEditButtons: ['tableHeader', 'tableRemove', '|', 'tableRows', 'tableColumns'],
				toolbarSticky: false,
				toolbarStickyOffset: 36,
				tableInsertHelper: false,
				videoAllowedTypes: ['mp4', 'quicktime', 'ogg', 'webm'],
				videoAllowedProviders: [],
				videoDefaultAlign: 'center', // when inline, this means not floated
				videoDefaultDisplay: 'inline',
				videoDefaultWidth: 500,
				videoEditButtons: ['videoReplace', 'videoRemove', '|', 'videoAlign', 'videoSize'],
				videoInsertButtons: ['videoBack', '|', 'videoUpload'],
				videoMaxSize: 4 * 1024 * 1024 * 1024, // 4G
				videoMove: true,
				videoUpload: false,
				videoUploadParam: 'upload',
				videoUploadURL: false,
				zIndex: XF.getElEffectiveZIndex(this.target) + 1,
				xfBbCodeAttachmentContextInput: this.options.attachmentContextInput,
			}

			FroalaEditor.DefineIconTemplate(
				'fa_svg',
				XF.Icon.getIcon('default', '[FA5NAME]'),
			)
			FroalaEditor.DefineIconTemplate(
				'fal_svg',
				XF.Icon.getIcon('fal', '[FA5NAME]'),
			)
			FroalaEditor.DefineIconTemplate(
				'far_svg',
				XF.Icon.getIcon('far', '[FA5NAME]'),
			)
			FroalaEditor.DefineIconTemplate(
				'fas_svg',
				XF.Icon.getIcon('fas', '[FA5NAME]'),
			)
			FroalaEditor.DefineIconTemplate(
				'fad_svg',
				XF.Icon.getIcon('fad', '[FA5NAME]'),
			)
			FroalaEditor.DefineIconTemplate(
				'fab_svg',
				XF.Icon.getIcon('fab', '[FA5NAME]'),
			)

			// FA5 overrides
			FroalaEditor.DefineIcon('insertVideo', { FA5NAME: 'video-plus' })

			if (this.uploadUrl)
			{
				const uploadParams = {
					_xfToken: XF.config.csrf,
					_xfResponseType: 'json',
					_xfWithData: 1,
				}

				config.fileUpload = true
				config.fileUploadParams = uploadParams
				config.fileUploadURL = this.uploadUrl

				config.imageUpload = true
				config.imageUploadParams = uploadParams
				config.imageUploadURL = this.uploadUrl
				config.imagePaste = true

				config.videoUpload = true
				config.videoUploadParams = uploadParams
				config.videoUploadURL = this.uploadUrl
			}
			else
			{
				config.imageInsertButtons = ['imageByURL']
			}

			const buttons = this.getButtonConfig()

			config = XF.extendObject({}, config, buttons)

			XF.trigger(this.target, XF.customEvent('editor:config', {
				config,
				editor: this,
			}))

			return config
		},

		getButtonConfig ()
		{
			let editorToolbars

			try
			{
				editorToolbars = JSON.parse(document.querySelector('.js-editorToolbars').innerHTML) || {}
			}
			catch (e)
			{
				console.error('Editor buttons data not valid: ', e)
				return
			}

			let editorDropdownButtons = {}
			let editorDropdowns

			try
			{
				editorDropdowns = JSON.parse(document.querySelector('.js-editorDropdowns').innerHTML) || {}
				for (const d of Object.keys(editorDropdowns))
				{
					if (editorDropdowns[d].buttons)
					{
						editorDropdownButtons[d] = editorDropdowns[d].buttons
					}
				}
			}
			catch (e)
			{
				console.error('Editor dropdowns data not valid: ', e)
			}

			const buttonManager = new XF.EditorButtons(this, editorToolbars, editorDropdownButtons)
			this.buttonManager = buttonManager

			if (!XF.isElementWithinDraftForm(this.target))
			{
				buttonManager.addRemovedButton('xfDraft')
			}

			const attachmentManager = this.getAttachmentManager()
			if (!attachmentManager || !attachmentManager.supportsVideoAudioUploads)
			{
				buttonManager.addRemovedButton('insertVideo')
			}

			if (this.options.buttonsRemove)
			{
				buttonManager.addRemovedButtons(this.options.buttonsRemove.split(','))
			}

			XF.trigger(this.target, XF.customEvent('editor:toolbar-buttons', {
				buttonManager,
				editor: this,
			}))

			return buttonManager.getToolbars()
		},

		editorInit ()
		{
			const ed = this.ed
			const t = this

			this.watchEditorHeight()

			if (this.form)
			{
				XF.on(this.form, 'ajax-submit:before', () => XF.EditorHelpers.sync(ed))
				XF.on(this.form, 'draft:beforesync', () => XF.EditorHelpers.sync(ed))

				XF.on(this.form, 'draft:complete', e =>
				{
					const { data } = e
					let draftButton
					let indicator

					if (ed.$tb.length && data.draft.saved === true)
					{
						draftButton = ed.$tb[0].querySelector('.fr-command.fr-btn[data-cmd=xfDraft]')
						if (draftButton)
						{
							indicator = draftButton.querySelector('.editorDraftIndicator')
							if (!indicator)
							{
								indicator = XF.createElementFromString('<b class="editorDraftIndicator"></b>')
								draftButton.appendChild(indicator)
							}

							setTimeout(() =>
							{
								indicator.classList.add('is-active')
							}, 50)
							setTimeout(() =>
							{
								indicator.classList.remove('is-active')
							}, 2500)
						}
					}
				})

				// detect image/video uploads from within Froala and potentially block submission if they're still happening
				XF.on(this.form, 'ajax-submit:before', function (e)
				{
					const $uploads = ed.$el.find('.fr-uploading')
					if ($uploads.length > 0 && !confirm(XF.phrase('files_being_uploaded_are_you_sure')))
					{
						e.preventDefault()
					}
				})

				ed.events.on('keydown', function (e)
				{
					if (e.key == 'Enter' && (XF.isMac() ? e.metaKey : e.ctrlKey))
					{
						e.preventDefault()
						XF.trigger(t.form, 'submit')
						return false
					}
				}, true)

				if (XF.isElementWithinDraftForm(this.form))
				{
					XF.Element.applyHandler(ed.$el[0], 'draft-trigger')
				}
			}

			// make images be inline automatically
			ed.events.on('image.inserted', function ($img)
			{
				$img.removeClass('fr-dib').addClass('fr-dii')
			})

			ed.events.on('image.beforePasteUpload', function (img)
			{
				const placeholderSrc = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='
				if (img.src == placeholderSrc)
				{
					return false
				}
			})

			let isPlainPaste = false

			ed.events.on('cut copy', function (e)
			{
				const range = ed.selection.ranges(0)
				if (!range || !range.commonAncestorContainer)
				{
					return
				}

				let container = range.commonAncestorContainer

				if (container.nodeType == Node.TEXT_NODE)
				{
					if (
						range.startOffset == 0
						&& range.endOffset == container.length
						&& container.parentNode != ed.$el[0]
					)
					{
						// if a complete bit of text is selected, try to select up the chain
						// as far as is equivalent
						container = container.parentNode
						while (
							container.parentNode != ed.$el[0]
							&& !container.previousSibling
							&& !container.nextSibling
						)
						{
							container = container.parentNode
						}
						range.selectNode(container)
					}
					else
					{
						container = container.parentNode
					}
				}

				const ps = container.querySelectorAll('p')
				ps.forEach(p => p.setAttribute('data-xf-p', '1'))

				setTimeout(function ()
				{
					ps.forEach(p => p.removeAttribute('data-xf-p'))
				}, 0)
			})

			ed.events.on('paste.before', function (e)
			{
				isPlainPaste = false

				if (e && e.clipboardData && e.clipboardData.getData)
				{
					let types = ''
					const clipboard_types = e.clipboardData.types

					if (ed.helpers.isArray(clipboard_types))
					{
						for (const type of clipboard_types)
						{
							types += type + ';'
						}
					}
					else
					{
						types = clipboard_types
					}

					if (
						/text\/plain/.test(types) && !ed.browser.mozilla
						&& !/text\/html/.test(types)
						&& (!/text\/rtf/.test(types) || !ed.browser.safari)
					)
					{
						isPlainPaste = true
					}
				}
			})

			ed.events.on('paste.beforeCleanup', function (content)
			{
				if (isPlainPaste)
				{
					content = content
						.replace(/\t/g, '    ')
						.replace(/ {2}/g, '&nbsp; ')
						.replace(/ {2}/g, '&nbsp; ')
						.replace(/> /g, '>&nbsp;')
				}

				// by the time the clean up happens, these line breaks have been stripped
				content = content.replace(/(<pre[^>]*>)([\s\S]+?)(<\/pre>)/g, function (match, open, inner, close)
				{
					inner = inner.replace(/\r?\n/g, '<br>')

					return open + inner + close
				})

				// P tags that have their top and bottom margins set to 0 act like our single line break versions,
				// so tag them as such
				content = content.replace(
					/<p([^>]+)margin-top:\s*0[a-z]*;\s*margin-bottom:\s*0[a-z]*;([^>]*)>([\s\S]*?)<\/p>/g,
					function (match, prefix, suffix, content)
					{
						return '<p' + prefix + suffix + ' data-xf-p="1">' + content + '</p>'
					},
				)

				content = content.replace(/<div(?=\s|>)/g, function (match)
				{
					return match + ' data-xf-p="1"'
				})

				// sometimes URLs are auto-linked when pasting using some browsers. because this interferes with unfurling
				// (an already linked URL cannot be unfurled) attempt to detect and extract the links to paste as text.
				// there are multiple variants depending on browser and OS.

				let match

				// variant 1: mostly *nix (including Apple)
				match = content.match(/^(?:<meta[^>]*>)?<a href=(?:'|")([^'"]*)\/?(?:'|")>\1<\/a>$/)
				if (match)
				{
					content = match[1].trim()
				}

				// variant 2: mostly Windows
				match = content.match(/<!--StartFragment--><a href=(?:'|")([^'"]*)\/?(?:'|")>[^<]+<\/a><!--EndFragment-->/)
				if (match)
				{
					content = match[1].trim()
				}

				content = XF.adjustHtmlForRte(content)

				const range = document.createRange()
				const fragment = range.createContextualFragment(content)
				const nodes = Array.from(fragment.childNodes)

				const removeAttributesFromNodeList = function (nodes)
				{
					let node, attrs, i, a

					for (i = 0; i < nodes.length; i++)
					{
						node = nodes[i]
						if (node instanceof Element)
						{
							if (node.hasAttributes())
							{
								attrs = node.attributes

								for (a = attrs.length - 1; a >= 0; a--)
								{
									const attr = attrs[a]
									if (attr.name.toLowerCase().substr(0, 2) == 'on'
										|| attr.name.toLowerCase() == 'style'
									)
									{
										node.removeAttribute(attr.name)
									}
								}
							}

							removeAttributesFromNodeList(node.childNodes)
						}
					}
				}

				removeAttributesFromNodeList(nodes)

				const div = XF.createElementFromString('<div></div>')
				div.append(...nodes)

				div.querySelectorAll('span.smilie').forEach(smilie =>
				{
					const shortname = smilie.getAttribute('data-shortname')

					if (smilie.getAttribute('data-smilie') && shortname)
					{
						const shortnameElement = document.createTextNode(XF.htmlspecialchars(shortname))
						shortnameElement.after(smilie)
						smilie.remove()
					}
					else
					{
						Array.from(smilie.childNodes).forEach(node => smilie.parentNode.insertBefore(node, smilie))
						smilie.remove()
					}
				})

				return div.innerHTML.trim()
			})

			ed.events.on('paste.afterCleanup', function (content)
			{
				return t.normalizePaste(content)
			})

			ed.events.on('paste.after', function ()
			{
				// keep the cursor visible if possible
				const range = ed.selection.ranges(0)
				if (!range || !range.getBoundingClientRect)
				{
					return
				}

				const rect = range.getBoundingClientRect()
				const docEl = document.documentElement
				const elRect = ed.$wp[0].getBoundingClientRect()

				if (
					rect.top < 0
					|| rect.left < 0
					|| rect.bottom > docEl.clientHeight
					|| rect.right > docEl.clientWidth
					|| rect.bottom > elRect.bottom
				)
				{
					setTimeout(function ()
					{
						t.scrollToCursor()
					}, 100)
				}

				XF.EditorHelpers.normalizeBrForEditor(ed.$el[0])
			})

			const mentionerOpts = {
				url: XF.getAutoCompleteUrl(),
			}
			this.mentioner = new XF.AutoCompleter(
				ed.$el[0],
				mentionerOpts,
				ed,
			)

			if (XF.config.shortcodeToEmoji)
			{
				const emojiOpts = {
					url: XF.canonicalizeUrl('index.php?misc/find-emoji'),
					at: ':',
					keepAt: false,
					insertMode: 'html',
					displayTemplate: '<div class="contentRow">' +
						'<div class="contentRow-figure contentRow-figure--emoji">{{{icon}}}</div>' +
						'<div class="contentRow-main contentRow-main--close">{{{text}}}' +
						'<div class="contentRow-minor contentRow-minor--smaller">{{{desc}}}</div></div>' +
						'</div>',
					beforeInsert (value, el)
					{
						XF.logRecentEmojiUsage(el.querySelector('img.smilie').dataset.shortname)

						return value
					},
				}
				this.emojiCompleter = new XF.AutoCompleter(
					ed.$el[0],
					emojiOpts,
					ed,
				)
			}

			this.setupUploads()

			if (!XF.isEditorEnabled())
			{
				let bbCodeInput = this.target.nextElementSibling
				while (bbCodeInput && (!bbCodeInput.matches('input[data-bb-code]')))
				{
					bbCodeInput = bbCodeInput.nextElementSibling
				}

				if (bbCodeInput)
				{
					ed.bbCode.toBbCode(bbCodeInput.value, true)
				}
				else
				{
					ed.bbCode.toBbCode(null, true)
				}
			}

			XF.EditorHelpers.setupBlurSelectionWatcher(ed)

			XF.on(this.target, 'control:enabled', function ()
			{
				ed.edit.on()
			})
			XF.on(this.target, 'control:disabled', function ()
			{
				ed.edit.off()
			})

			XF.on(this.target, 'control:enabled', function ()
			{
				ed.edit.on()
				if (ed.bbCode && ed.bbCode.isBbCodeView())
				{
					const $button = ed.$tb.find('.fr-command[data-cmd=xfBbCode]')
					$button.removeClass('fr-disabled')
				}
				else
				{
					ed.toolbar.enable()
				}
			})
			XF.on(this.target, 'control:disabled', function ()
			{
				ed.edit.off()
				ed.toolbar.disable()
				ed.$tb.find(' > .fr-command').addClass('fr-disabled')
			})

			XF.trigger(this.target, XF.customEvent('editor:init', {
				ed,
				editor: this,
			}))

			XF.layoutChange()
		},

		focus ()
		{
			XF.EditorHelpers.focus(this.ed)
		},

		blur ()
		{
			XF.EditorHelpers.blur(this.ed)
		},

		normalizePaste (content)
		{
			// FF has a tendency of maintaining whitespace from the content which gives odd pasting results
			content = content.replace(/(<(ul|li|p|div)>)\s+/ig, '$1')
			content = content.replace(/\s+(<\/(ul|li|p|div)>)/ig, '$1')

			// some pastes from Chrome insert this span unexpectedly which causes extra bullet points
			content = content
				.replace(/<span>&nbsp;<\/span>/ig, ' ')
				.replace(/(<\/li>)\s+(<li)/ig, '$1$2')

			const ed = this.ed
			const range = document.createRange()
			const fragment = range.createContextualFragment(content)
			const fragWrapper = XF.createElementFromString('<div></div>')
			const nodes = Array.from(fragment.childNodes)

			fragWrapper.append(...nodes)

			fragWrapper.querySelectorAll('table').forEach(table =>
			{
				table.style.width = '100%'
				const wrapper = XF.createElementFromString('<div class="bbTable"></div>')
				table.parentNode.insertBefore(wrapper, table)
				wrapper.appendChild(table)

				table.querySelectorAll('[colspan], [rowspan]').forEach(element =>
				{
					element.removeAttribute('colspan')
					element.removeAttribute('rowspan')
				})

				let maxColumns = 0
				table.querySelectorAll('> tbody > tr').forEach(row =>
				{
					const columnCount = row.querySelectorAll('> td, > th').length
					maxColumns = Math.max(maxColumns, columnCount)
				})

				table.querySelectorAll('> tbody > tr').forEach(row =>
				{
					const cells = row.querySelectorAll('> td, > th')
					const columnCount = cells.length
					if (columnCount < maxColumns)
					{
						const tag = columnCount && cells[0].tagName === 'TH' ? '<th />' : '<td />'
						for (let i = columnCount; i < maxColumns; i++)
						{
							const newCell = document.createElement(tag)
							row.appendChild(newCell)
						}
					}
				})
			})

			const elementsToReplace = fragWrapper.querySelectorAll('code, del, ins, sub, sup')
			elementsToReplace.forEach(element =>
			{
				const newContent = document.createTextNode(element.innerHTML)
				element.parentNode.replaceChild(newContent, element)
			})

			// We expose H2 - H4 primarily. If we find an H1, consider that to be the biggest heading and
			// shift the others down 1. Otherwise, leave as is.
			let hasH1 = false

			const h1Headings = fragWrapper.querySelectorAll('h1')
			h1Headings.forEach(heading =>
			{
				hasH1 = true
				const newHeading = document.createElement('h2')
				newHeading.appendChild(...heading.childNodes)
				heading.parentNode.replaceChild(newHeading, heading)
			})

			const hMap = {
				H2: hasH1 ? 'H3' : 'H2',
				H3: hasH1 ? 'H4' : 'H3',
				H4: 'H4',
				H5: 'H4',
				H6: 'H4',
			}

			const otherHeadings = fragWrapper.querySelectorAll('h2, h3, h4, h5, h6')
			otherHeadings.forEach(heading =>
			{
				const tagName = heading.tagName
				const newHeading = document.createElement(hMap[tagName])
				newHeading.appendChild(...heading.childNodes)
				heading.parentNode.replaceChild(newHeading, heading)
			})

			const preElement = fragWrapper.querySelector('pre')
			if (preElement)
			{
				let inner = preElement.innerHTML

				inner = inner
					.replace(/\r?\n/g, '<br>')
					.replace(/\t/g, '    ')
					.replace(/ {2}/g, '&nbsp; ')
					.replace(/ {2}/g, '&nbsp; ')
					.replace(/> /g, '>&nbsp;')
					.replace(/<br> /g, '<br>&nbsp;')

				const newElement = document.createElement('div')
				newElement.innerHTML = inner + '<br>'

				preElement.parentNode.replaceChild(newElement, preElement)
			}

			if (!ed.opts.imagePaste)
			{
				// If image pasting is disabled in Froala, it will remove all pasted images, even if they
				// will be just links. Allow linked images to remain in this case.
				fragWrapper.querySelectorAll('img[data-fr-image-pasted]').forEach(image =>
				{
					const src = image.getAttribute('src')
					if (src.match(/https?:\/\//i))
					{
						image.removeAttribute('data-fr-image-pasted')
					}
				})
			}

			const brTags = fragWrapper.querySelectorAll('br')
			brTags.forEach((br) =>
			{
				const parents = []
				let parent = br.parentNode
				while (parent && parent !== fragWrapper)
				{
					parents.push(parent)
					parent = parent.parentNode
				}

				if (parents.length === 0)
				{
					// Already at the root of the paste
					return
				}

				const hasBlockParent = parents.some((el) =>
				{
					return ed.node.isBlock(el)
				})

				if (hasBlockParent)
				{
					// If we have a block parent, we can't move this
					return
				}

				let shiftTarget = []
				let shiftIsEl = false
				let clone
				let ref = br
				let topParent = parents[parents.length - 1]

				do
				{
					while (ref.nextSibling)
					{
						clone = ref.nextSibling.cloneNode(true)
						if (shiftIsEl)
						{
							shiftTarget.push(clone)
						}
						else
						{
							shiftTarget = shiftTarget.concat(clone)
						}
						ref.parentNode.removeChild(ref.nextSibling)
					}
					ref = ref.parentNode
					if (!ref || ref === fragWrapper)
					{
						break
					}

					clone = ref.cloneNode()
					clone.innerHTML = ''
					shiftTarget.forEach((el) =>
					{
						clone.appendChild(el)
					})
					shiftTarget = [clone]
					shiftIsEl = true
				}
				while (ref.parentNode && ref.parentNode !== fragWrapper)

				br.parentNode.removeChild(br)

				topParent.after(...shiftTarget)
				topParent.after(document.createElement('br'))
			})

			// Look for root p tags to add extra line breaks since we treat a p as a single break.
			// Try to detect an internal paste and don't add it there
			let copiedText = ''
			const pastedText = fragWrapper.textContent.replace(/\s/g, '')

			try
			{
				copiedText = (ed.win.localStorage.getItem('fr-copied-text') || '').replace(/\s/g, '')
			}
			catch (e)
			{
				// ignore
			}

			if (copiedText !== pastedText)
			{
				fragWrapper.querySelectorAll('p:not([data-xf-p])').forEach(p =>
				{
					if (p.nextSibling)
					{
						const newP = document.createElement('p')
						p.parentNode.insertBefore(newP, p.nextSibling)
					}
				})
			}

			fragWrapper.querySelectorAll('p').forEach(p => p.removeAttribute('data-xf-p'))

			const frag = Array.from(fragWrapper.childNodes)
			let output = document.createElement('div')
			let wrapTarget = null

			for (const node of frag)
			{
				if (node.nodeType === Node.ELEMENT_NODE && ed.node.isBlock(node))
				{
					output.appendChild(node)
					wrapTarget = null
				}
				else if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'BR')
				{
					if (!wrapTarget)
					{
						// this would generally be two <br> tags in a row
						output.appendChild(document.createElement('p'))
					}

					wrapTarget = null
				}
				else
				{
					if (!wrapTarget)
					{
						wrapTarget = document.createElement('p')
						output.appendChild(wrapTarget)
					}

					wrapTarget.appendChild(node)
				}
			}

			const children = output.children
			if (children.length === 1 && (children[0].tagName === 'P' || children[0].tagName === 'DIV'))
			{
				output = children[0]
			}

			return XF.EditorHelpers.normalizeBrForEditor(output.innerHTML)
		},

		watchEditorHeight ()
		{
			const ed = this.ed

			XF.on(window, 'resize', () =>
			{
				const heightLimits = this.getHeightLimits()
				ed.opts.heightMin = heightLimits[0]
				ed.opts.heightMax = heightLimits[1]
				ed.size.refresh()
				XF.layoutChange()
			}, { passive: true })

			ed.events.on('focus', () => this.scrollToCursorAfterPendingResize())

			// const  getHeight = () => ed.$el.height()
			const getHeight = () => ed.$wp.height()
			let height = getHeight()
			const layoutChangeIfNeeded = () =>
			{
				const newHeight = getHeight()
				if (height != newHeight)
				{
					height = newHeight
					XF.layoutChange()
				}
			}

			ed.events.on('keyup', layoutChangeIfNeeded)
			ed.events.on('commands.after', layoutChangeIfNeeded)
			ed.events.on('html.set', layoutChangeIfNeeded)
			ed.events.on('init', layoutChangeIfNeeded)
			ed.events.on('initialized', layoutChangeIfNeeded)
		},

		getHeightLimits ()
		{
			let maxHeightOption = this.options.maxHeight
			const minHeightOption = this.options.minHeight
			let maxHeight = null
			let minHeight = null

			if (this.target.closest('.overlay'))
			{
				maxHeightOption = 0.1 // don't grow the editor at all if we are in an overlay
			}

			if (maxHeightOption)
			{
				let viewHeight = document.documentElement.clientHeight
				let height

				// we can't reliably detect when the keyboard displays, so we need to act like it's always displayed
				if (/(iPad|iPhone|iPod)/g.test(navigator.userAgent))
				{
					viewHeight -= 250
				}

				if (maxHeightOption > 0)
				{
					if (maxHeightOption <= 1) // example: 0.8 = 80%
					{
						height = viewHeight * maxHeightOption
					}
					else
					{
						height = maxHeightOption // example 250 = 250px
					}
				}
				else // example: -100 = window height - 100 px
				{
					height = viewHeight + maxHeightOption
				}

				maxHeight = Math.floor(height)
				maxHeight = Math.max(maxHeight, 150)
			}

			if (minHeightOption && maxHeight)
			{
				minHeight = Math.min(minHeightOption, maxHeight)
				if (minHeight == maxHeight)
				{
					minHeight -= 1 // prevents an unnecessary scrollbar
				}
			}

			return [minHeight, maxHeight]
		},

		setupUploads ()
		{
			const t = this
			const ed = this.ed

			ed.events.on('file.uploaded', function (response)
			{
				this.popups.hide('file.insert')
				this.events.focus()
				return t.handleUploadSuccess(response)
			})

			ed.events.on('file.error', function (details, response)
			{
				this.popups.hide('file.insert')
				t.handleUploadError(details, response)
				this.events.focus()
				return false
			})

			if (!this.uploadUrl)
			{
				ed.events.on('image.beforeUpload', function ()
				{
					return false // prevent uploading
				})
				ed.events.on('file.beforeUpload', function ()
				{
					return false // prevent uploading
				})
				ed.events.on('video.beforeUpload', function ()
				{
					return false // prevent uploading
				})
			}

			ed.events.on('image.error', function (details, response)
			{
				if (!response)
				{
					return // not an uploaded image
				}

				this.popups.hide('image.insert')
				t.handleUploadError(details, response)
				return false
			})

			ed.events.on('video.error', function (details, response)
			{
				if (!response)
				{
					return // not an uploaded image
				}

				this.popups.hide('video.insert')
				t.handleUploadError(details, response)
				return false
			})

			ed.events.on('image.uploaded', function (response)
			{
				const onError = function ()
				{
					ed.image.remove()
					ed.popups.hide('image.insert')
					ed.events.focus()
					return false
				}

				const onSuccess = function ()
				{
					return true
				}

				return t.handleUploadSuccess(response, onError, onSuccess)
			})

			ed.events.on('video.uploaded', function (response)
			{
				const onError = function ()
				{
					ed.video.remove()
					ed.popups.hide('video.insert')
					ed.events.focus()
					return false
				}

				const onSuccess = function ()
				{
					return true
				}

				return t.handleUploadSuccess(response, onError, onSuccess)
			})

			const videoImageInsert = function ($el, response)
			{
				if (!response)
				{
					return
				}

				let json
				try
				{
					json = JSON.parse(response)
				}
				catch (e)
				{
					return
				}

				if ($el.hasClass('fr-video'))
				{
					const $video = $el.find('video')

					$video
						.attr('data-xf-init', 'video-init')
						.attr('style', '')
						.empty()

					$el = $video
				}

				if (json.attachment)
				{
					// clean up the data attributes that were added from our JSON response
					const id = json.attachment.attachment_id
					const attrs = $el[0].attributes
					const re = /^data-(?!xf-init)/
					for (let i = attrs.length - 1; i >= 0; i--)
					{
						if (re.test(attrs[i].nodeName))
						{
							$el.removeAttr(attrs[i].nodeName)
						}
					}

					$el.attr('data-attachment', 'full:' + id)
				}
			}

			ed.events.on('image.inserted video.inserted', videoImageInsert)
			ed.events.on('image.replaced video.replaced', videoImageInsert)

			ed.events.on('image.loaded', function ($img)
			{
				// try to prevent automatic editing of an image once inserted

				if (!ed.popups.isVisible('image.edit'))
				{
					// ... but not if we're not in the edit mode
					return
				}

				const $editorImage = ed.image.get()
				if (!$editorImage || $editorImage[0] != $img[0])
				{
					// ... and only if it's for this image
					return
				}

				$editorImage.attr('data-size', `${$editorImage[0].naturalWidth}x${$editorImage[0].naturalHeight}`)

				ed.image.exitEdit(true)

				const range = ed.selection.ranges(0)
				range.setStartAfter($img[0])
				range.collapse(true)

				const selection = ed.selection.get()
				selection.removeAllRanges()
				selection.addRange(range)

				ed.events.focus()
				t.scrollToCursor()
			})

			ed.events.on('video.loaded', function ($video)
			{
				// try to prevent automatic editing of a video once inserted

				if (!ed.popups.isVisible('video.edit'))
				{
					// ... but not if we're not in the edit mode
					return
				}

				const $editorVideo = ed.video.get()
				if (!$editorVideo || $editorVideo[0] != $video[0])
				{
					// ... and only if it's for this video
					return
				}

				ed.events.trigger('video.hideResizer')
				ed.popups.hide('video.edit')

				const range = ed.selection.ranges(0)
				range.setStartAfter($video[0])
				range.collapse(true)

				const selection = ed.selection.get()
				selection.removeAllRanges()
				selection.addRange(range)

				ed.events.focus()
				t.scrollToCursor()
			})

			ed.events.on('popups.show.image.edit', function ()
			{
				const $editorImage = ed.image.get()

				if (!$editorImage.length || !$editorImage.hasClass('smilie'))
				{
					return
				}

				ed.image.exitEdit(true)
				ed.selection.save()

				setTimeout(function ()
				{
					ed.selection.restore()
				}, 0)
			})
		},

		handleUploadSuccess (response, onError, onSuccess)
		{
			let json

			try
			{
				json = JSON.parse(response)
			}
			catch (e)
			{
				json = {
					status: 'error',
					errors: [XF.phrase('oops_we_ran_into_some_problems')],
				}
			}

			if (json.status && json.status == 'error')
			{
				XF.alert(json.errors[0])
				return onError ? onError(json) : false
			}

			const attachmentManager = this.getAttachmentManager()
			if (attachmentManager && json.attachment)
			{
				attachmentManager.insertUploadedRow(json.attachment)
				return onSuccess ? onSuccess(json, attachmentManager) : false
			}

			return false
		},

		handleUploadError (details, response)
		{
			let json

			try
			{
				json = JSON.parse(response)
			}
			catch (e)
			{
				json = null
			}

			if (!json || !json.errors)
			{
				json = {
					status: 'error',
					errors: [XF.phrase('oops_we_ran_into_some_problems')],
				}
			}

			XF.alert(json.errors[0])
		},

		getAttachmentManager ()
		{
			const $match = this.target.closest('[data-xf-init~=attachment-manager]')
			if ($match)
			{
				return XF.Element.getHandler($match, 'attachment-manager')
			}

			return null
		},

		isBbCodeView ()
		{
			if (this.ed.bbCode && this.ed.bbCode.isBbCodeView)
			{
				return this.ed.bbCode.isBbCodeView()
			}
			else
			{
				return false
			}
		},

		insertContent (html, text)
		{
			const ed = this.ed

			if (this.isBbCodeView())
			{
				if (typeof text !== 'undefined')
				{
					ed.bbCode.insertBbCode(text)
				}
			}
			else
			{
				this.focus()
				ed.undo.saveStep()
				ed.html.insert(html)
				ed.undo.saveStep()
				XF.Element.initialize(ed.$el[0])

				XF.EditorHelpers.normalizeAfterInsert(ed)
			}

			this.scrollToCursor()
			this.scrollToCursorAfterPendingResize()
		},

		replaceContent (html, text)
		{
			const ed = this.ed

			if (this.isBbCodeView())
			{
				if (typeof text !== 'undefined')
				{
					ed.bbCode.replaceBbCode(text)
				}
			}
			else
			{
				ed.html.set(html)
			}
		},

		scrollToCursor ()
		{
			const ed = this.ed

			if (this.isBbCodeView())
			{
				XF.autofocus(ed.bbCode.getTextArea())
				ed.$box[0].scrollIntoView(true)
			}
			else
			{
				this.focus()

				const $edBox = ed.$box
				const $edWrapper = ed.$wp
				const selEl = ed.selection.endElement()
				const selBottom = selEl.getBoundingClientRect().bottom
				let selVisible = true
				let winHeight = XF.windowHeight()

				if (XF.browser.ios)
				{
					// assume the keyboard takes up approximately this much space
					winHeight -= 250
				}

				if (selBottom < 0 || selBottom >= winHeight)
				{
					// outside the window
					selVisible = false
				}
				if ($edWrapper && selVisible)
				{
					const wrapperRect = $edWrapper[0].getBoundingClientRect()

					if (selBottom > wrapperRect.bottom || selBottom < wrapperRect.top)
					{
						// inside the window, but need to scroll the wrapper
						selVisible = false
					}
				}

				if (!selVisible)
				{
					const boxPos = $edBox[0].getBoundingClientRect()
					if (boxPos.top < 0 || boxPos.bottom >= winHeight)
					{
						if (!XF.browser.ios)
						{
							// don't add in iOS because it shouldn't apply to small screens but this doesn't trigger
							// in iOS as expected
							$edBox.addClass('is-scrolling-to')
						}
						$edBox[0].scrollIntoView(true)
						$edBox.removeClass('is-scrolling-to')
					}

					if ($edWrapper)
					{
						const info = ed.position.getBoundingRect().top

						// attempt to put this in the middle of the screen.
						// 50px offset to compensate for sticky form footer.
						// note this doesn't seem to work in iOS at all likely due to webkit limitations.
						if (info > $edWrapper.offset().top - ed.helpers.scrollTop() + $edWrapper.height() - 50)
						{
							$edWrapper.scrollTop(info + $edWrapper.scrollTop() - ($edWrapper.height() + $edWrapper.offset().top) + ed.helpers.scrollTop() + (winHeight / 2))
						}
					}
					else
					{
						selEl.scrollIntoView()
					}
				}
			}
		},

		scrollToCursorAfterPendingResize (forceTrigger)
		{
			// This is to ensure that we keep the cursor visible after the onscreen keyboard appears
			const scrollWatcher = function ()
			{
				if (scrollTimer)
				{
					clearTimeout(scrollTimer)
				}
				scrollTimer = setTimeout(scrollTo, 100)
			}
			// by trying to determine when this happens and scroll to it.
			const self = this
			const ed = this.ed
			let scrollTimer
			const onResize = function ()
			{
				XF.off(window, 'resize', onResize)
				XF.on(window, 'scroll', scrollWatcher)

				if (scrollTimer)
				{
					clearTimeout(scrollTimer)
				}
				scrollTimer = setTimeout(scrollTo, 500)
			}
			const scrollTo = function ()
			{
				XF.off(window, 'scroll', scrollWatcher)

				if (ed.core.hasFocus())
				{
					self.scrollToCursor()
				}
			}

			XF.on(window, 'resize', onResize)
			setTimeout(function ()
			{
				XF.off(window, 'resize', onResize)
			}, 2000)

			if (forceTrigger)
			{
				scrollTimer = setTimeout(scrollTo, 1000)
			}
		},

		base64ToBytes (base64String, sliceSize)
		{
			sliceSize = sliceSize || 512

			const byteCharacters = atob(base64String)
			const byteArrays = []

			for (let offset = 0; offset < byteCharacters.length; offset += sliceSize)
			{
				const slice = byteCharacters.slice(offset, offset + sliceSize)

				const byteNumbers = new Array(slice.length)
				for (let i = 0; i < slice.length; i++)
				{
					byteNumbers[i] = slice.charCodeAt(i)
				}

				const byteArray = new Uint8Array(byteNumbers)

				byteArrays.push(byteArray)
			}

			return byteArrays
		},

		editorSupportsUploads ()
		{
			return (this.ed.opts.imageInsertButtons.indexOf('imageUpload') !== -1)
		},

		imageMatchesBase64Encoding ($img)
		{
			const src = $img.attr('src')
			return src.match(/^data:(image\/([a-z0-9]+));base64,(.*)$/)
		},

		replaceBase64ImageWithUpload ($img)
		{
			if ($img.hasClass('smilie'))
			{
				// it's one of our smilies or emojis so skip it
				return
			}

			let match, contentType, extension, base64String

			match = this.imageMatchesBase64Encoding($img)

			if (match)
			{
				contentType = match[1]
				extension = match[2]
				base64String = match[3]

				if (this.ed.opts.imageAllowedTypes.indexOf(extension) === -1)
				{
					$img[0].remove()
					return
				}

				if (this.editorSupportsUploads())
				{
					const file = new Blob(this.base64ToBytes(base64String), {
						type: contentType,
					})

					// skip very small data URIs
					if (file.size > 1024)
					{
						this.ed.image.upload([file])
					}
				}
				else
				{
					$img[0].remove()
				}
			}
		},

		isInitialized ()
		{
			return this.ed ? true : false
		},
	})

	XF.EditorButtons = XF.create({
		xfEd: null,
		buttonClasses: null,

		toolbars: {},
		dropdowns: {},
		removeButtons: null,

		recalculateNeeded: true,

		__construct (xfEd, toolbars, dropdowns)
		{
			this.xfEd = xfEd

			// initialize this as empty for each editor instance
			this.removeButtons = []

			if (toolbars)
			{
				this.toolbars = toolbars
			}
			if (dropdowns)
			{
				this.dropdowns = dropdowns
			}
		},

		addToolbar (name, buttons)
		{
			this.toolbars[name] = buttons
			this.recalculateNeeded = true
		},

		adjustToolbar (name, callback)
		{
			const buttons = this.toolbars[name]
			if (buttons)
			{
				this.toolbars[name] = callback(buttons, name, this)
				this.recalculateNeeded = true
				return true
			}
			else
			{
				return false
			}
		},

		adjustToolbars (callback)
		{
			for (const k of Object.keys(this.toolbars))
			{
				this.adjustToolbar(k, callback)
			}
		},

		getToolbar (name)
		{
			const toolbars = this.getToolbars()
			return toolbars[name]
		},

		getToolbars ()
		{
			this.recalculateIfNeeded()

			if (XF.EditorHelpers.isPreviewAvailable(this.xfEd.target))
			{
				for (const toolbarSize of Object.keys(this.toolbars))
				{
					this.toolbars[toolbarSize].preview = {
						buttons: ['xfPreview'],
						align: 'right',
					}
				}
			}

			return this.toolbars
		},

		addDropdown (name, buttons)
		{
			this.dropdowns[name] = buttons
			this.recalculateNeeded = true
		},

		adjustDropdown (name, callback)
		{
			const buttons = this.dropdowns[name]
			if (buttons)
			{
				this.dropdowns[name] = callback(buttons, name, this)
				this.recalculateNeeded = true
				return true
			}
			else
			{
				return false
			}
		},

		adjustDropdowns (callback)
		{
			for (const k of Object.keys(this.dropdowns))
			{
				this.adjustDropdown(k, callback)
			}
		},

		getDropdown (name)
		{
			const dropdowns = this.getDropdowns()
			return dropdowns[name]
		},

		getDropdowns ()
		{
			this.recalculateIfNeeded()

			return this.dropdowns
		},

		addRemovedButton (name)
		{
			this.removeButtons.push(name)
			this.recalculateNeeded = true
		},

		addRemovedButtons (buttons)
		{
			for (const button of buttons)
			{
				this.removeButtons.push(button)
			}
			this.recalculateNeeded = true
		},

		recalculateIfNeeded ()
		{
			if (this.recalculateNeeded)
			{
				this.recalculate()
			}
		},

		recalculate ()
		{
			const removeList = this.removeButtons
			const buttonClasses = this.getButtonClasses()
			let remove
			let toolbarKey
			let dropdownKey
			let group
			let i

			function removeFromButtons (buttons, removeName)
			{
				if (!buttons.filter)
				{
					return []
				}

				if (typeof removeName == 'string' && buttonClasses[removeName])
				{
					removeName = buttonClasses[removeName]
				}

				if (typeof removeName == 'string')
				{
					removeName = removeName.split('|')
				}

				return buttons.filter(function (button)
				{
					return !(removeName.indexOf(button) >= 0)
				})
			}

			// remove disallowed buttons
			for (i = 0; i < removeList.length; i++)
			{
				remove = removeList[i]

				for (toolbarKey of Object.keys(this.toolbars))
				{
					for (group of Object.keys(this.toolbars[toolbarKey]))
					{
						this.toolbars[toolbarKey][group]['buttons'] = removeFromButtons(this.toolbars[toolbarKey][group]['buttons'], remove)
					}
				}
				for (dropdownKey of Object.keys(this.dropdowns))
				{
					this.dropdowns[dropdownKey] = removeFromButtons(this.dropdowns[dropdownKey], remove)
				}
			}

			// remove empty dropdowns
			for (dropdownKey of Object.keys(this.dropdowns))
			{
				if (!this.dropdowns[dropdownKey].length)
				{
					for (toolbarKey of Object.keys(this.toolbars))
					{
						for (group of Object.keys(this.toolbars[toolbarKey]))
						{
							this.toolbars[toolbarKey][group]['buttons'] = removeFromButtons(this.toolbars[toolbarKey][group]['buttons'], dropdownKey)
						}
					}
				}
			}

			this.recalculateNeeded = false
		},

		getButtonClasses ()
		{
			if (!this.buttonClasses)
			{
				this.buttonClasses = {
					_basic: ['bold', 'italic', 'underline', 'strikeThrough'],
					_extended: ['textColor', 'fontFamily', 'fontSize', 'xfInlineCode', 'paragraphFormat'],
					_link: ['insertLink'],
					_align: ['align', 'alignLeft', 'alignCenter', 'alignRight', 'alignJustify'],
					_list: ['formatOL', 'formatUL', 'outdent', 'indent'],
					_indent: ['outdent', 'indent'],
					_smilies: ['xfSmilie'],
					_image: ['insertImage', 'xfInsertGif'],
					_media: ['insertVideo', 'xfMedia'],
					_block: ['xfQuote', 'xfCode', 'xfSpoiler', 'xfInlineSpoiler', 'insertTable', 'insertHR'],
				}
			}

			return this.buttonClasses
		},
	})

	XF.EditorHelpers = {
		// note: these will generally be overridden from the option
		toolbarSizes: {
			SM: 420,
			MD: 550,
			LG: 800,
		},

		setupBlurSelectionWatcher (ed)
		{
			const $el = ed.$el
			let trackSelection = false
			const trackKey = 'xf-ed-blur-sel'
			let range

			const inputdown = e =>
			{
				if (!trackSelection)
				{
					// editor isn't known to be focused
					return
				}
				if (ed.$el[0] == e.target || ed.$el[0].contains(e.target))
				{
					// event triggering is the editor or within it, so should maintain selection
					return
				}
				if (!ed.selection.inEditor())
				{
					// the current selection isn't in the editor, so nothing to save
					return
				}

				range = ed.selection.ranges(0)
			}

			XF.on(document, 'mousedown', inputdown)
			XF.on(document, 'keydown', inputdown)

			ed.events.on('blur', function ()
			{
				ed.$box.removeClass('is-focused')

				if (range)
				{
					$el.data(trackKey, range)
				}
				else
				{
					$el.removeData(trackKey)
				}

				trackSelection = false
				range = null
			}, true)
			ed.events.on('focus', function ()
			{
				ed.$box.addClass('is-focused')
				trackSelection = true
				range = null

				setTimeout(function ()
				{
					$el.removeData(trackKey)
				}, 0)
			})
			ed.events.on('commands.before', function (cmd)
			{
				const cmdConfig = FroalaEditor.COMMANDS[cmd]
				if (cmdConfig && (typeof cmdConfig.focus == 'undefined' || cmdConfig.focus))
				{
					XF.EditorHelpers.restoreMaintainedSelection(ed)
					// focus will happen in the command
				}
			})
		},

		restoreMaintainedSelection (ed)
		{
			const $el = ed.$el
			const blurSelection = $el.data('xf-ed-blur-sel')

			if (!ed.selection.inEditor())
			{
				if (blurSelection)
				{
					ed.markers.remove()
					ed.markers.place(blurSelection, true, 0)
					ed.markers.place(blurSelection, false, 0)
					ed.selection.restore()
				}
				else
				{
					ed.selection.setAtEnd(ed.el)
					ed.selection.restore()
				}
			}
		},

		focus (ed)
		{
			XF.EditorHelpers.restoreMaintainedSelection(ed)
			ed.$tb.addClass('is-focused')
			ed.events.focus()
		},

		blur (ed)
		{
			ed.$el[0].blur()
			ed.$tb.removeClass('is-focused')
			ed.selection.clear()
		},

		sync (ed)
		{
			ed.$oel.val(ed.html.get())
		},

		wrapSelectionText (ed, before, after, save, inline)
		{
			if (save)
			{
				ed.selection.save()
			}
			ed.undo.saveStep()

			const wrapper = document.createElement('div')
			const markers = Array.from(ed.el.querySelectorAll('.fr-marker'))
			let html = null
			let selectedHtml

			if (!ed.selection.isCollapsed())
			{
				// markers may be touched by getSelected
				wrapper.appendChild(markers[markers.length - 1])

				selectedHtml = XF.EditorHelpers.bypassBrowserShims(ed, () => ed.html.getSelected())

				if (/<p>/i.test(selectedHtml))
				{
					// avoid injecting additional new-lines when selected spans new-lines
					if (inline)
					{
						const frag = document.createElement('div')
						frag.innerHTML = selectedHtml
						const p = frag.querySelector('p')
						p.insertAdjacentHTML('afterbegin', XF.htmlspecialchars(before))
						p.append(...Array.from(wrapper.children))
						p.insertAdjacentHTML('beforeend', XF.htmlspecialchars(after))

						// special case that an entire single line has been selected, and instead select the contents of that line
						if (frag.children.length === 1 && frag.children[0].children.length === 1)
						{
							html = p.innerHTML
						}
						else
						{
							html = frag.innerHTML
						}
					}
					else
					{
						selectedHtml += wrapper.innerHTML
						inline = true
					}
				}
				else
				{
					selectedHtml += wrapper.innerHTML
				}
			}
			else
			{
				wrapper.appendChild(markers[0])
				wrapper.appendChild(markers[markers.length - 1])
				selectedHtml = wrapper.innerHTML
			}

			if (html === null)
			{
				html = XF.htmlspecialchars(before) + selectedHtml + XF.htmlspecialchars(after)
			}

			if (!inline)
			{
				html = '<p>' + html + '</p>'
			}

			ed.html.insert(html)

			ed.selection.restore()
			ed.placeholder.hide()
			ed.undo.saveStep()
			XF.EditorHelpers.normalizeAfterInsert(ed)
		},

		insertCode (ed, type, code)
		{
			let tag
			let lang
			let output

			switch (type.toLowerCase())
			{
				case '':
					tag = 'CODE'
					lang = ''
					break
				default:
					tag = 'CODE'
					lang = type.toLowerCase()
					break
			}

			code = code.replace(/&/g, '&amp;').replace(/</g, '&lt;')
				.replace(/>/g, '&gt;').replace(/"/g, '&quot;')
				.replace(/\t/g, '    ')
				.replace(/\n /g, '\n&nbsp;')
				.replace(/ {2}/g, '&nbsp; ')
				.replace(/ {2}/g, ' &nbsp;') // need to do this twice to catch a situation where there are an odd number of spaces
				.replace(/\n/g, '</p><p>')

			output = '[' + tag + (lang ? '=' + lang : '') + ']' + code + '[/' + tag + ']'
			if (output.match(/<\/p>/i))
			{
				output = '<p>' + output + '</p>'
				output = output.replace(/<p><\/p>/g, '<p><br></p>')
			}

			ed.undo.saveStep()
			ed.html.insert(output)
			ed.undo.saveStep()

			XF.EditorHelpers.normalizeAfterInsert(ed)
		},

		insertSpoiler (ed, title)
		{
			let open
			if (title)
			{
				open = '[SPOILER="' + title + '"]'
			}
			else
			{
				open = '[SPOILER]'
			}

			XF.EditorHelpers.wrapSelectionText(ed, open, '[/SPOILER]', true)
		},

		normalizeBrForEditor (content)
		{
			const asString = typeof content === 'string'
			let fragWrapper

			if (asString)
			{
				fragWrapper = XF.createElementFromString('<div></div>')
				fragWrapper.innerHTML = content
			}
			else
			{
				fragWrapper = content
			}

			const checkNodeMatch = (node, elementType) =>
			{
				if (node.nodeType !== Node.ELEMENT_NODE)
				{
					return false
				}

				return (node.matches(elementType)
					&& node.className === ''
					&& !node.hasAttribute('id')
					&& !node.hasAttribute('style'))
			}

			// Workaround editor behaviour that a <br> should not be the first or last child of a <p> tag
			// <p><br>...</p>; editor can delete too many lines
			// <p>...<br></p>; editor can delete too few lines

			Array.from(fragWrapper.childNodes).forEach(child =>
			{
				if (child.nodeType !== Node.ELEMENT_NODE)
				{
					return
				}

				if (child.matches('p'))
				{
					if (child.childNodes.length !== 1)
					{
						return
					}

					const firstChild = child.childNodes[0]
					if (checkNodeMatch(firstChild, 'span'))
					{
						child.innerHTML = firstChild.innerHTML
					}
				}
			})

			Array.from(fragWrapper.childNodes).forEach(child =>
			{
				if (child.nodeType !== Node.ELEMENT_NODE)
				{
					return
				}

				if (child.matches('p'))
				{
					if (child.childNodes.length <= 1)
					{
						return
					}

					const firstChild = child.childNodes[0]
					if (checkNodeMatch(firstChild, 'br'))
					{
						const parentElement = child.parentNode
						const firstChild = child.firstChild

						const pElement = document.createElement('p')
						pElement.appendChild(firstChild)

						parentElement.insertBefore(pElement, child)
					}
				}
			})

			Array.from(fragWrapper.childNodes).forEach(child =>
			{
				if (child.nodeType !== Node.ELEMENT_NODE)
				{
					return
				}

				if (child.matches('p'))
				{
					if (child.childNodes.length <= 1)
					{
						return
					}

					const lastChild = child.childNodes[child.childNodes.length - 1]
					if (checkNodeMatch(lastChild, 'br'))
					{
						lastChild.remove()
					}
				}
			})

			return asString ? fragWrapper.innerHTML : fragWrapper
		},

		normalizeAfterInsert (ed)
		{
			const selected = ed.html.getSelected()

			if (/<br>\s*<\/p>/.test(selected))
			{
				XF.EditorHelpers.normalizeBrForEditor(ed.$el[0])
				// remove the last undo step and replace it with the corrected html version
				ed.undo_index--
				ed.undo_stack.pop()
				ed.undo.saveStep()
			}
		},

		isPreviewAvailable (textarea)
		{
			if (!textarea.dataset.previewUrl && !textarea.closest('form').dataset.previewUrl)
			{
				return false
			}

			return true
		},

		dialogs: {},

		loadDialog (ed, dialog)
		{
			const dialogs = XF.EditorHelpers.dialogs
			if (dialogs[dialog])
			{
				dialogs[dialog].show(ed)
			}
			else
			{
				console.error('Unknown dialog \'' + dialog + '\'')
			}
		},

		bypassBrowserShims (ed, callback, browsers = ['mozilla'])
		{
			const oldValues = {}

			for (const browser of browsers)
			{
				oldValues[browser] = ed.browser[browser]
				ed.browser[browser] = 0
			}

			try
			{
				return callback()
			}
			finally
			{
				for (const browserKey of Object.keys(oldValues))
				{
					ed.browser[browserKey] = oldValues[browserKey]
				}
			}
		},
	}

	XF.EditorDialog = XF.create({
		ed: null,
		overlay: null,
		dialog: null,
		cache: true,

		__construct (dialog)
		{
			this.dialog = dialog
		},

		show (ed)
		{
			this.ed = ed

			ed.selection.save()

			XF.loadOverlay(XF.canonicalizeUrl('index.php?editor/dialog&dialog=' + this.dialog), {
				beforeShow: this.beforeShow.bind(this),
				afterShow: this.afterShow.bind(this),
				init: this.init.bind(this),
				cache: this.cache,
			})
		},

		init (overlay)
		{
			overlay.on('overlay:hidden', () =>
			{
				if (this.ed)
				{
					this.ed.markers.remove()
				}
			})

			this._init(overlay)
		},

		_init (overlay) {},

		beforeShow (overlay)
		{
			this.overlay = overlay

			this._beforeShow(overlay)
		},

		_beforeShow (overlay) {},

		afterShow (overlay)
		{
			this._afterShow(overlay)

			overlay.overlay.querySelector('textarea, input').focus()
		},

		_afterShow (overlay) {},
	})

	XF.EditorDialogMedia = XF.extend(XF.EditorDialog, {
		_beforeShow (overlay)
		{
			document.querySelector('#editor_media_url').value = ''
		},

		_init (overlay)
		{
			XF.on(document.querySelector('#editor_media_form'), 'submit', this.submit.bind(this))
		},

		submit (e)
		{
			e.preventDefault()

			const ed = this.ed
			const overlay = this.overlay

			XF.ajax(
				'POST',
				XF.canonicalizeUrl('index.php?editor/media'),
				{ url: document.querySelector('#editor_media_url').value },
				data =>
				{
					if (data.matchBbCode)
					{
						ed.selection.restore()
						ed.undo.saveStep()
						ed.html.insert(XF.htmlspecialchars(data.matchBbCode))
						ed.undo.saveStep()
						XF.EditorHelpers.normalizeAfterInsert(ed)
						overlay.hide()
					}
					else if (data.noMatch)
					{
						XF.alert(data.noMatch)
					}
					else
					{
						ed.selection.restore()
						overlay.hide()
					}
				},
			)
		},
	})

	XF.EditorDialogSpoiler = XF.extend(XF.EditorDialog, {
		_beforeShow (overlay)
		{
			document.querySelector('#editor_spoiler_title').value = ''
		},

		_init (overlay)
		{
			XF.on(document.querySelector('#editor_spoiler_form'), 'submit', this.submit.bind(this))
		},

		submit (e)
		{
			e.preventDefault()

			const ed = this.ed
			const overlay = this.overlay

			ed.selection.restore()
			XF.EditorHelpers.insertSpoiler(ed, document.querySelector('#editor_spoiler_title').value)

			overlay.hide()
		},
	})

	XF.EditorDialogCode = XF.extend(XF.EditorDialog, {
		_beforeShow (overlay)
		{
			this.ed.$el.blur()
		},

		_afterShow (overlay)
		{
			const container = overlay.container
			const codeMirror = container.querySelector('.CodeMirror')
			const ed = this.ed
			let instance

			const switcher = container.querySelector('[data-xf-init~="code-editor-switcher-container"]')
			XF.trigger(switcher, 'code-editor:reinit')

			if (codeMirror)
			{
				instance = codeMirror.CodeMirror
			}

			let selectedText

			if (ed.selection.isCollapsed())
			{
				selectedText = ''
			}
			else
			{
				const selected = ed.html.getSelected()
					.replace(/&nbsp;/gmi, ' ')
					.replace(/\u200B/g, '')
					.replace(/(<\/(p|div|pre|blockquote|h[1-6]|tr|th|ul|ol|li)>)\s*/gi, '$1\n')
					.replace(/<(li|p)><br><\/\1>\s*/gi, '\n')
					.replace(/<br>\s*/gi, '\n')

				const range = document.createRange()
				const fragment = range.createContextualFragment(selected)

				selectedText = fragment.firstChild.textContent.trim()
			}

			// weird FF behavior where inserting code wouldn't replace the current selection without this
			ed.selection.save()

			if (instance)
			{
				instance.getDoc().setValue(selectedText)
				instance.focus()
			}
			else
			{
				const codeEditor = container.querySelector('.js-codeEditor')
				codeEditor.value = selectedText
				codeEditor.focus()
			}
		},

		_init (overlay)
		{
			XF.on(document.querySelector('#editor_code_form'), 'submit', this.submit.bind(this))
		},

		submit (e)
		{
			e.preventDefault()

			const ed = this.ed
			const overlay = this.overlay

			const codeMirror = overlay.container.querySelector('.CodeMirror')
			if (codeMirror)
			{
				const instance = codeMirror.CodeMirror
				const doc = instance.getDoc()

				instance.save()
				doc.setValue('')

				instance.setOption('mode', '')
			}

			const type = document.querySelector('#editor_code_type')
			const code = document.querySelector('#editor_code_code')

			ed.selection.restore()
			XF.EditorHelpers.insertCode(ed, type.value, code.value)

			overlay.hide()

			code.value = ''
			type.value = ''
		},
	})

	XF.editorStart = {
		started: false,
		custom: [],

		startAll ()
		{
			if (!XF.editorStart.started)
			{
				XF.editorStart.setupLanguage()
				XF.editorStart.registerOverrides()
				XF.editorStart.registerToolbarSizes()
				XF.editorStart.registerCommands()
				XF.editorStart.registerCustomCommands()
				XF.editorStart.registerEditorDropdowns()
				XF.editorStart.registerDialogs()

				XF.trigger(document, 'editor:first-start')

				XF.editorStart.started = true
			}
		},

		setupLanguage ()
		{
			const dir = XF.isRtl() ? 'rtl' : 'ltr'
			let lang

			try
			{
				lang = JSON.parse(document.querySelector('.js-editorLanguage').innerHTML) || {}
			}
			catch (e)
			{
				console.error(e)
				lang = {}
			}

			FroalaEditor.LANGUAGE['xf'] = {
				translation: lang,
				direction: dir ? dir.toLowerCase() : 'ltr',
			}
		},

		registerOverrides ()
		{
			const originalHelpers = FroalaEditor.MODULES.helpers

			FroalaEditor.MODULES.helpers = function (ed, ...args)
			{
				const helpers = originalHelpers.apply(this, [ed, ...args])
				const sanitizeURL = helpers.sanitizeURL

				helpers.sanitizeURL = function (url)
				{
					const res = sanitizeURL(url)
					return res
						.replace(/["]/g, '%22')
						.replace(/[']/g, '%27')
				}

				helpers.screenSize = function ()
				{
					let width

					function sizeHelper (width, sizeName)
					{
						ed.$box.data('size', sizeName)
						return FroalaEditor[XF.hasOwn(FroalaEditor, sizeName) ? sizeName : 'LG']
					}

					try
					{
						width = ed.$box.width()
						const toolbarSizes = XF.EditorHelpers.toolbarSizes

						// if the editor isn't visible, we won't get a width, so loop up to find
						// the first thing we can get a width from
						if (width <= 0)
						{
							let ref = ed.$box[0]
							while ((ref = ref.parentNode))
							{
								width = ref.clientWidth
								if (width > 0)
								{
									const css = window.getComputedStyle(ref)
									width -= parseInt(css.paddingLeft, 10) + parseInt(css.paddingRight, 10)
									if (width > 0)
									{
										break
									}
								}
							}
						}

						if (width < toolbarSizes.SM)
						{
							return sizeHelper(width, 'XS')
						}

						if (width < toolbarSizes.MD)
						{
							return sizeHelper(width, 'SM')
						}

						if (width < toolbarSizes.LG)
						{
							return sizeHelper(width, 'MD')
						}

						if (width < toolbarSizes.LG + 50)
						{
							return sizeHelper(width, 'LG')
						}

						return sizeHelper(width, 'XL')
					}
					catch (ex)
					{
						// if in doubt...
						return sizeHelper(width, 'XS')
					}
				}

				return helpers
			}
		},

		registerToolbarSizes ()
		{
			let editorToolbarSizes

			try
			{
				editorToolbarSizes = JSON.parse(document.querySelector('.js-editorToolbarSizes').innerHTML) || {}
			}
			catch (e)
			{
				console.error('Toolbar sizes data not valid: ', e)
				return
			}

			XF.EditorHelpers.toolbarSizes = editorToolbarSizes
		},

		commands:
			{
				xfQuote: ['quote-right', {
					title: 'Quote',
					icon: 'xfQuote',
					undo: true,
					focus: true,
					callback ()
					{
						const editor = this

						// gets information about the path back to the editor root, including the first
						// quote found
						function getNodePathInfo (node)
						{
							const original = node
							let quote = null

							if (node.tagName == 'BLOCKQUOTE')
							{
								quote = node
							}

							while (node.parentNode && node.parentNode !== editor.el)
							{
								node = node.parentNode

								if (!quote && node.tagName == 'BLOCKQUOTE')
								{
									quote = node
								}
							}

							return {
								original,
								quote,
								root: node,
							}
						}

						editor.selection.save()
						editor.html.wrap(true, true, true, true)
						editor.selection.restore()

						let blocks = editor.selection.blocks()
						const blocksInfo = []
						let createQuote = true
						let b
						let info

						// if the temp div is selected, just assume it's the first block that we want
						if (blocks.length == 1 && blocks[0].matches('.fr-temp-div'))
						{
							blocks = [editor.$el.find('p').get(0)]
						}

						for (b = 0; b < blocks.length; b++)
						{
							info = getNodePathInfo(blocks[b])
							if (info.quote)
							{
								createQuote = false
							}

							blocksInfo.push(info)
						}

						editor.selection.save()

						if (createQuote)
						{
							const quote = document.createElement('blockquote')
							blocksInfo[0].root.parentNode.insertBefore(quote, blocksInfo[0].root)

							for (b = 0; b < blocksInfo.length; b++)
							{
								quote.append(blocksInfo[b].root)
							}
						}
						else
						{
							let quote

							for (b = 0; b < blocksInfo.length; b++)
							{
								quote = blocksInfo[b].quote
								if (quote)
								{
									quote.outerHTML = quote.innerHTML
								}
							}
						}

						editor.html.unwrap()
						editor.selection.restore()
					},
				}],

				xfCode: ['code', {
					title: 'Code',
					icon: 'xfCode',
					undo: true,
					focus: true,
					callback ()
					{
						XF.EditorHelpers.loadDialog(this, 'code')
					},
				}],

				xfInlineCode: ['terminal', {
					title: 'Inline Code',
					icon: 'xfInlineCode',
					undo: true,
					focus: true,
					refresh: function refresh ($btn)
					{
						const format = this.format.is('code')
						$btn.toggleClass('fr-active', format).attr('aria-pressed', format)
					},
					callback ()
					{
						this.format.toggle('code')
					},
				}],

				xfMedia: ['photo-video', {
					title: 'Media',
					icon: 'xfMedia',
					undo: true,
					focus: true,
					callback ()
					{
						XF.EditorHelpers.loadDialog(this, 'media')
					},
				}],

				xfSpoiler: ['eye-slash', {
					title: 'Spoiler',
					icon: 'xfSpoiler',
					undo: true,
					focus: true,
					callback ()
					{
						XF.EditorHelpers.loadDialog(this, 'spoiler')
					},
				}],

				xfInlineSpoiler: ['mask', {
					title: 'Inline Spoiler',
					icon: 'xfInlineSpoiler',
					undo: true,
					focus: true,
					callback ()
					{
						XF.EditorHelpers.wrapSelectionText(this, '[ISPOILER]', '[/ISPOILER]', true, true)
					},
				}],

				xfSmilie: ['smile', {
					title: 'Smilies',
					icon: 'xfSmilie',
					undo: false,
					focus: false,
					refreshOnCallback: false,
					callback ()
					{
						setTimeout(() => this.xfSmilie.showMenu(), 0)
					},
				}],

				xfInsertGif: ['xfInsertGif', {
					title: 'Insert GIF',
					icon: 'xfInsertGif',
					undo: false,
					focus: false,
					refreshOnCallback: false,
					callback ()
					{
						setTimeout(() => this.xfInsertGif.showMenu(), 0)
					},
				}],

				xfDraft: ['save', {
					type: 'dropdown',
					title: 'Drafts',
					focus: true,
					undo: false,
					options: {
						xfDraftSave: 'Save Draft',
						xfDraftDelete: 'Delete Draft',
					},
					html ()
					{
						const options = {
							xfDraftSave: 'Save Draft',
							xfDraftDelete: 'Delete Draft',
						}

						let o = '<ul class="fr-dropdown-list">'

						for (const key in options)
						{
							o += '<li><a class="fr-command" data-cmd="xfDraft" data-param1="' + key + '">' + this.language.translate(options[key]) + '</a></li>'
						}

						o += '</ul>'

						return o
					},
					callback (cmd, val)
					{
						const form = this.$el[0].closest('form')
						if (!form)
						{
							console.error('No parent form to find draft handler')
							return
						}

						const draftHandler = XF.Element.getHandler(form, 'draft')
						if (!draftHandler)
						{
							console.error('No draft handler on parent form')
							return
						}

						if (val == 'xfDraftSave')
						{
							draftHandler.triggerSave()
						}
						else if (val == 'xfDraftDelete')
						{
							draftHandler.triggerDelete()
						}
					},
				}],

				xfBbCode: ['brackets', {
					title: 'Toggle BB Code',
					icon: 'xfBbCode',
					undo: false,
					focus: false,
					forcedRefresh: true,
					callback ()
					{
						this.bbCode.toggle()
					},
				}],

				xfPreview: ['file-search', {
					title: 'Preview',
					icon: 'xfPreview',
					undo: false,
					focus: false,
					forcedRefresh: true,
					callback ()
					{
						this.contentPreview.toggle()
					},
				}],
			},

		registerCommands ()
		{
			const t = this
			let cmd

			FroalaEditor.PLUGINS.xfInsertGif = function (editor)
			{
				let initialized = false
				let loaded = false
				let menu
				let menuScroll
				let scrollTop = 0

				function showMenu ()
				{
					selectionSave()

					XF.EditorHelpers.blur(editor)

					const btn = editor.$tb.find('.fr-command[data-cmd="xfInsertGif"]')[0]
					if (!initialized)
					{
						initialized = true

						let menuHtml = document.querySelector('.js-xfEditorMenu').innerHTML.trim()
						menuHtml = Mustache.render(menuHtml, { href: XF.canonicalizeUrl('index.php?editor/insert-gif') })

						const range = document.createRange()
						const fragment = range.createContextualFragment(menuHtml)

						menu = fragment.querySelector('.menu')
						menu.classList.add('menu--gif')

						btn.insertAdjacentElement('afterend', menu)
						btn.dataset.xfClick = 'menu'

						const handler = XF.Event.getElementHandler(btn, 'menu', 'click')

						XF.on(btn, 'menu:complete', () =>
						{
							menuScroll = menu.querySelector('.menu-scroller')

							if (!loaded)
							{
								loaded = true

								initMenuContents()

								const gifSearch = menu.querySelector('.js-gifSearch')
								XF.on(gifSearch, 'input', performSearch)

								XF.on(menu.querySelector('.js-gifCloser'), 'click', () => XF.EditorHelpers.focus(editor))

								editor.events.on('commands.mousedown', $el =>
								{
									if ($el.data('cmd') != 'xfInsertGif')
									{
										handler.close()
									}
								})

								XF.on(menu, 'menu:closed', () =>
								{
									scrollTop = menuScroll.scrollTop
								})
							}

							menuScroll.scroll({ top: scrollTop })
						})

						XF.on(menu, 'menu:closed', () =>
						{
							setTimeout(() =>
							{
								editor.markers.remove()
							}, 50)
						})
					}

					const clickHandlers = XF.DataStore.get(btn, 'xf-click-handlers')
					if (clickHandlers && clickHandlers.menu)
					{
						clickHandlers.menu.toggle()
					}
				}

				function initMenuContents ()
				{
					const gifObserver = new IntersectionObserver(onGifIntersection, {
						root: menuScroll,
						rootMargin: '0px 0px 100px 0px',
					})
					menuScroll.querySelectorAll('.js-gif img:not(.js-observed)').forEach(gif =>
					{
						gif.classList.add('js-observed')
						gifObserver.observe(gif)
					})

					const loadingObserver = new IntersectionObserver(onLoadingIntersection, {
						root: menuScroll,
						rootMargin: '0px 0px 50px 0px',
					})
					menuScroll.querySelectorAll('.js-gifLoadMore').forEach(loadMore =>
					{
						loadingObserver.observe(loadMore)
					})

					menuScroll.querySelectorAll('.js-gif').forEach(gif => XF.on(gif, 'click', insertGif))
				}

				function insertGif (e)
				{
					const target = e.currentTarget
					const img = target.querySelector('img')
					const parent = img.parentNode

					if (parent.classList.contains('is-loading'))
					{
						return
					}

					parent.classList.add('is-loading')

					const image = img.dataset.insert
					const imageEl = document.createElement('img')
					imageEl.setAttribute('src', image)
					imageEl.classList.add('fr-fic', 'fr-dii', 'fr-draggable')
					imageEl.alt = img.alt

					const insert = function ()
					{
						selectionRestore()
						XF.EditorHelpers.focus(editor)
						editor.undo.saveStep()
						editor.html.insert(imageEl.outerHTML)
						editor.undo.saveStep()
						selectionSave()
						XF.EditorHelpers.blur(editor)
						XF.EditorHelpers.normalizeAfterInsert(editor)

						if (menu)
						{
							menu.querySelector('.js-gifCloser').click()
						}

						parent.classList.remove('is-loading')
					}

					if (!imageEl.complete)
					{
						XF.on(imageEl, 'load', insert)
					}
					else
					{
						insert()
					}
				}

				function onGifIntersection (changes, observer)
				{
					let target

					for (const entry of changes)
					{
						target = entry.target
						if (entry.isIntersecting)
						{
							lazyLoadGif(target)
						}
						else
						{
							lazyUnloadGif(target)
						}
					}
				}

				function onLoadingIntersection (changes, observer)
				{
					let target

					for (const entry of changes)
					{
						if (!entry.isIntersecting)
						{
							continue
						}

						target = entry.target
						loadMore(target)
						observer.unobserve(entry.target)
					}
				}

				function loadVisibleImages (rowOrEvent)
				{
					let row = rowOrEvent

					if (rowOrEvent instanceof Event)
					{
						row = rowOrEvent.target
					}

					if (!row.offsetParent)
					{
						return
					}

					const visibleRect = row.getBoundingClientRect()
					const visibleBottom = visibleRect.bottom + 100 // 100px offset for visible detection
					row.childNodes.forEach(child =>
					{
						if (child.nodeType !== Node.ELEMENT_NODE)
						{
							return
						}

						const childRect = child.getBoundingClientRect()
						if (childRect.bottom < visibleRect.top)
						{
							// area is above what's visible
							return
						}
						if (childRect.top > visibleBottom)
						{
							// area is below what's visible, so assume everything else is
							return false
						}

						// otherwise we're visible, so look for smilies here
						child.querySelectorAll('.js-gif img').forEach(toLoad =>
						{
							const smilieRect = toLoad.getBoundingClientRect()

							if (smilieRect.top <= visibleBottom)
							{
								// gif is before the end of the visible area, so load
								lazyLoadGif(toLoad)
							}
						})
					})
				}

				function loadMore (target)
				{
					if (target.dataset.loading)
					{
						return
					}

					target.dataset.loading = '1'

					XF.ajax('GET', target.dataset.href, data =>
					{
						if (!data.html)
						{
							// TODO: should remove the loading element as likely indicates no more GIFs
							return
						}

						XF.setupHtmlInsert(data.html, html =>
						{
							let insert

							if (html.matches('.js-gifContainer'))
							{
								insert = html
							}
							else
							{
								insert = html.querySelector('.js-gifContainer')
							}

							const container = insert.closest('.js-gifContainer')

							setTimeout(() =>
							{
								target.outerHTML = container.innerHTML
								initMenuContents()
							}, 100)
						})
					})
				}

				function lazyLoadGif (toLoad)
				{
					if (toLoad.dataset.loaded)
					{
						return
					}

					const dataSrc = toLoad.getAttribute('data-src')
					const src = toLoad.getAttribute('src')

					toLoad.setAttribute('src', dataSrc)
					toLoad.setAttribute('data-src', src)
					toLoad.dataset.loaded = '1'
				}

				function lazyUnloadGif (toLoad)
				{
					if (!toLoad.dataset.loaded)
					{
						return
					}

					const dataSrc = toLoad.getAttribute('data-src')
					const src = toLoad.getAttribute('src')

					toLoad.setAttribute('src', dataSrc)
					toLoad.setAttribute('data-src', src)
					toLoad.dataset.loaded = ''
				}

				let timer

				function performSearch ()
				{
					const input = this
					const fullList = menu.querySelector('.js-gifFullRow')
					const searchResults = menu.querySelector('.js-gifSearchRow')

					clearTimeout(timer)

					timer = setTimeout(function ()
					{
						const value = input.value

						if (!value || value.length < 2)
						{
							XF.display(searchResults, 'none')
							XF.display(fullList)
							loadVisibleImages(fullList)
							return
						}

						const url = XF.canonicalizeUrl('index.php?editor/insert-gif/search')
						XF.ajax('GET', url, { q: value }, data =>
						{
							if (!data.html)
							{
								return
							}

							XF.setupHtmlInsert(data.html, html =>
							{
								XF.display(fullList, 'none')
								searchResults.innerHTML = html.outerHTML
								XF.display(searchResults)
								menuScroll.scroll({ top: 0 })

								initMenuContents()
							})
						})
					}, 300)
				}

				function selectionSave ()
				{
					editor.selection.save()
				}

				function selectionRestore ()
				{
					editor.selection.restore()
				}

				return {
					showMenu,
				}
			}

			FroalaEditor.PLUGINS.xfSmilie = function (editor)
			{
				let initialized = false
				let loaded = false
				let menu
				let menuScroll
				let scrollTop = 0
				let flashTimeout
				let logTimeout

				function showMenu ()
				{
					selectionSave()

					XF.EditorHelpers.blur(editor)

					const btn = editor.$tb.find('.fr-command[data-cmd="xfSmilie"]')[0]
					if (!initialized)
					{
						initialized = true

						let menuHtml = document.querySelector('.js-xfEditorMenu').innerHTML.trim()
						menuHtml = Mustache.render(menuHtml, { href: XF.canonicalizeUrl('index.php?editor/smilies-emoji') })

						const range = document.createRange()
						const fragment = range.createContextualFragment(menuHtml)

						menu = fragment.querySelector('.menu')
						menu.classList.add('menu--emoji')

						btn.insertAdjacentElement('afterend', menu)
						btn.dataset.xfClick = 'menu'

						const handler = XF.Event.getElementHandler(btn, 'menu', 'click')

						XF.on(btn, 'menu:complete', () =>
						{
							menuScroll = menu.querySelector('.menu-scroller')

							if (!loaded)
							{
								loaded = true

								const observer = new IntersectionObserver(onEmojiIntersection, {
									root: menuScroll,
									rootMargin: '0px 0px 100px 0px',
								})
								menuScroll.querySelectorAll('span.smilie--lazyLoad').forEach(smilie =>
								{
									observer.observe(smilie)
								})

								menuScroll.querySelectorAll('.js-emoji').forEach(emoji => XF.on(emoji, 'click', insertEmoji))

								const emojiSearch = menu.querySelector('.js-emojiSearch')
								XF.on(emojiSearch, 'input', performSearch)

								XF.on(menu.querySelector('.js-emojiCloser'), 'click', () => XF.EditorHelpers.focus(editor))

								XF.on(document, 'recent-emoji:logged', updateRecentEmoji)

								editor.events.on('commands.mousedown', $el =>
								{
									if ($el.data('cmd') != 'xfSmilie')
									{
										handler.close()
									}
								})

								XF.on(menu, 'menu:closed', () =>
								{
									scrollTop = menuScroll.scrollTop
								})
							}

							menuScroll.scroll({
								top: scrollTop,
							})
						})

						XF.on(menu, 'menu:closed', () => setTimeout(() => editor.markers.remove(), 50))
					}

					const clickHandlers = XF.DataStore.get(btn, 'xf-click-handlers')
					if (clickHandlers && clickHandlers.menu)
					{
						clickHandlers.menu.toggle()
					}
				}

				function insertEmoji (e)
				{
					const target = e.currentTarget
					const html = target.innerHTML

					if (target.classList.contains('smilie--lazyLoad'))
					{
						return
					}

					XF.EditorHelpers.bypassBrowserShims(editor, () =>
					{
						selectionRestore()
						XF.EditorHelpers.focus(editor)
						editor.undo.saveStep()
						editor.html.insert(html)
						editor.undo.saveStep()
						selectionSave()
						XF.EditorHelpers.blur(editor)
						XF.EditorHelpers.normalizeAfterInsert(editor)
					})

					if (menu)
					{
						const insertRow = menu.querySelector('.js-emojiInsertedRow')
						insertRow.querySelector('.js-emojiInsert').innerHTML = html
						XF.Transition.addClassTransitioned(insertRow, 'is-active')

						clearTimeout(flashTimeout)
						flashTimeout = setTimeout(() => XF.Transition.removeClassTransitioned(insertRow, 'is-active'), 1500)
					}

					clearTimeout(logTimeout)
					logTimeout = setTimeout(() => XF.logRecentEmojiUsage(target.dataset.shortname), 1500)
				}

				function onEmojiIntersection (changes, observer)
				{
					let target

					for (const entry of changes)
					{
						if (!entry.isIntersecting)
						{
							continue
						}

						target = entry.target
						lazyLoadEmoji(target)
						observer.unobserve(entry.target)
					}
				}

				function loadVisibleImages (rowOrEvent, assumeVisible)
				{
					let row = rowOrEvent

					if (rowOrEvent instanceof Event)
					{
						row = rowOrEvent.target
					}

					if (!assumeVisible && !row.offsetParent)
					{
						return
					}

					const visibleRect = row.getBoundingClientRect()
					const visibleBottom = visibleRect.bottom + 100 // 100px offset for visible detection

					row.childNodes.forEach(child =>
					{
						if (child.nodeType !== Node.ELEMENT_NODE)
						{
							return
						}

						const childRect = child.getBoundingClientRect()

						if (childRect.bottom < visibleRect.top)
						{
							// area is above what's visible
							return
						}
						if (childRect.top > visibleBottom)
						{
							// area is below what's visible, so assume everything else is
							return false
						}

						// otherwise we're visible, so look for smilies here
						child.querySelectorAll('span.smilie--lazyLoad').forEach(toLoad =>
						{
							const smilieRect = toLoad.getBoundingClientRect()

							if (smilieRect.top <= visibleBottom)
							{
								// smilie is before the end of the visible area, so load
								lazyLoadEmoji(toLoad)
							}
						})
					})
				}

				function lazyLoadEmoji (toLoad)
				{
					const image = XF.createElement('img', {
						className: toLoad.getAttribute('class').replace(/(\s|^)smilie--lazyLoad(\s|$)/, ' '),
						alt: toLoad.getAttribute('data-alt'),
						title: toLoad.getAttribute('title'),
						src: toLoad.getAttribute('data-src'),
						dataset: { shortname: toLoad.dataset.shortname }
					})

					const replace = () =>
					{
						window.requestAnimationFrame(() =>
						{
							toLoad.outerHTML = image.outerHTML
						})
					}

					if (image.complete)
					{
						XF.on(image, 'load', replace)
					}
					else
					{
						replace()
					}
				}

				let timer

				function performSearch ()
				{
					const input = this
					const fullList = menu.querySelector('.js-emojiFullList')
					const searchResults = menu.querySelector('.js-emojiSearchResults')

					clearTimeout(timer)

					timer = setTimeout(() =>
					{
						const value = input.value

						if (!value || value.length < 2)
						{
							XF.display(searchResults, 'none')
							XF.display(fullList)
							loadVisibleImages(fullList)
							return
						}

						const url = XF.canonicalizeUrl('index.php?editor/smilies-emoji/search')
						XF.ajax('GET', url, { q: value }, data =>
						{
							if (!data.html)
							{
								return
							}

							XF.setupHtmlInsert(data.html, html =>
							{
								XF.display(fullList, 'none')
								searchResults.innerHTML = html.outerHTML
								XF.display(searchResults)

								searchResults.querySelectorAll('.js-emoji').forEach(emoji =>
								{
									XF.on(emoji, 'click', insertEmoji)
								})
							})
						})
					}, 300)
				}

				function updateRecentEmoji ()
				{
					let i
					const recent = XF.getRecentEmojiUsage()
					const recentHeader = menuScroll.querySelector('.js-recentHeader')
					const recentBlock = menuScroll.querySelector('.js-recentBlock')
					const recentList = recentBlock.querySelector('.js-recentList')
					const emojiLists = menuScroll.querySelectorAll('.js-emojiList')

					if (!recent)
					{
						return
					}

					const newList = recentList.cloneNode(true)
					const newListArr = []

					newList.innerHTML = ''

					for (i in recent)
					{
						const shortname = recent[i]
						let emoji

						emojiLists.forEach(list =>
						{
							const emoji = list.querySelector(`.js-emoji[data-shortname="${ shortname }"]`)
							if (emoji)
							{
								const clonedItem = emoji.closest('li').cloneNode(true)
								newListArr.push(clonedItem)
								return false
							}
						})
					}

					for (i in newListArr)
					{
						const li = newListArr[i]
						newList.appendChild(li)
					}

					recentList.innerHTML = newList.innerHTML

					recentList.querySelectorAll('.js-emoji').forEach(emoji =>
					{
						XF.on(emoji, 'click', insertEmoji)
					})

					if (recentBlock.classList.contains('is-hidden'))
					{
						XF.display(recentBlock, 'none')
						recentBlock.classList.remove('is-hidden')
						recentHeader.classList.remove('is-hidden')
						XF.Animate.fadeDown(recentBlock, {
							speed: XF.config.speed.fast,
						})
					}

					loadVisibleImages(recentList, true)
				}

				function selectionSave ()
				{
					editor.selection.save()
				}

				function selectionRestore ()
				{
					editor.selection.restore()
				}

				return {
					showMenu,
				}
			}

			XF.extendObject(FroalaEditor.DEFAULTS, {
				xfBbCodeAttachmentContextInput: 'attachment_hash_combined',
			})
			FroalaEditor.PLUGINS.bbCode = function (ed)
			{
				let _isBbCodeView = false

				function getButton ()
				{
					return ed.$tb.find('.fr-command[data-cmd=xfBbCode]')[0]
				}

				function getBbCodeBox ()
				{
					const $oel = ed.$oel

					let bbCodeBox = $oel.data('xfBbCodeBox')
					if (!bbCodeBox)
					{
						const borderAdjust = parseInt(ed.$wp.css('border-bottom-width'), 10)
							+ parseInt(ed.$wp.css('border-top-width'), 10)

						bbCodeBox = XF.createElementFromString('<textarea class="input" style="display: none"></textarea>')
						bbCodeBox.setAttribute('aria-label', XF.htmlspecialchars(XF.phrase('rich_text_box')))
						Object.assign(bbCodeBox.style, {
							minHeight: ed.opts.heightMin ? (ed.opts.heightMin + borderAdjust) + 'px' : null,
							maxHeight: ed.opts.heightMax ? ed.opts.heightMax + 'px' : null,
							height: ed.opts.height ? (ed.opts.height + borderAdjust) + 'px' : null,
							padding: ed.$el.css('padding'),
						})
						bbCodeBox.setAttribute('name', $oel.data('original-name'))
						$oel.data('xfBbCodeBox', bbCodeBox)
						ed.$wp.after(bbCodeBox)

						XF.on(bbCodeBox, 'focus', e => ed.$box.addClass('is-focused'))
						XF.on(bbCodeBox, 'blur', e => ed.$box.removeClass('is-focused'))

						setTimeout(() =>
						{
							XF.Element.applyHandler(bbCodeBox, 'textarea-handler')
							XF.Element.applyHandler(bbCodeBox, 'user-mentioner')
							XF.Element.applyHandler(bbCodeBox, 'emoji-completer')

							if (XF.isElementWithinDraftForm(bbCodeBox))
							{
								XF.Element.applyHandler(bbCodeBox, 'draft-trigger')
							}
						}, 100)
					}

					return bbCodeBox
				}

				function btnsToDisable (button)
				{
					return Array.from(ed.$tb[0].querySelectorAll('.fr-btn-grp .fr-command, .fr-more-toolbar .fr-command'))
						.filter(btn => !btn.isSameNode(button) && !btn.getAttribute('data-cmd').startsWith('more') && btn.getAttribute('data-cmd') !== 'xfPreview')
				}

				function toBbCode (bbCode, skipFocus)
				{
					const bbCodeBox = getBbCodeBox()

					const apply = function (bbCode, skipFocus)
					{
						_isBbCodeView = true

						let button

						ed.undo.saveStep()
						ed.$el.blur()

						button = getButton()

						btnsToDisable(button).forEach(btn => btn.classList.add('fr-disabled'))
						button.classList.add('fr-active')

						ed.$wp.css('display', 'none')
						ed.$oel.attr('disabled', 'disabled') // Froala jQuery doesn't implement prop

						bbCodeBox.value = bbCode
						XF.display(bbCodeBox)
						bbCodeBox.disabled = false

						XF.trigger(bbCodeBox, 'autosize')

						if (!skipFocus)
						{
							bbCodeBox.focus()
						}

						XF.setIsEditorEnabled(false)
					}

					if (typeof bbCode == 'string')
					{
						apply(bbCode, skipFocus)
					}
					else
					{
						XF.ajax(
							'POST',
							XF.canonicalizeUrl('index.php?editor/to-bb-code'),
							{ html: ed.html.get() },
							function (data) { apply(data.bbCode, skipFocus) },
						)
					}
				}

				function toHtml (html)
				{
					const bbCodeBox = getBbCodeBox()

					const apply = function (html)
					{
						_isBbCodeView = false

						const button = getButton()

						btnsToDisable(button).forEach(btn => btn.classList.remove('fr-disabled'))
						button.classList.remove('fr-active')

						ed.$oel.removeAttr('disabled')
						ed.html.set(html)

						XF.display(bbCodeBox, 'none')
						bbCodeBox.disabled = true
						ed.$wp.css('display', '')

						ed.events.focus()
						ed.undo.saveStep()
						ed.size.refresh()

						XF.setIsEditorEnabled(true)
						XF.layoutChange()
					}

					if (typeof html == 'string')
					{
						apply(html)
					}
					else
					{
						const params = { bb_code: bbCodeBox.value }

						const form = ed.$el[0].closest('form')
						if (form)
						{
							if (form[ed.opts.xfBbCodeAttachmentContextInput])
							{
								params.attachment_hash_combined = form[ed.opts.xfBbCodeAttachmentContextInput].value
							}
						}

						XF.ajax(
							'POST',
							XF.canonicalizeUrl('index.php?editor/to-html'),
							params,
							function (data) { apply(data.editorHtml) },
						)
					}
				}

				function toggle ()
				{
					if (_isBbCodeView)
					{
						toHtml()
					}
					else
					{
						toBbCode()
					}
				}

				function isBbCodeView ()
				{
					return _isBbCodeView
				}

				function getToggleableButtons ()
				{
					return btnsToDisable(getButton())
				}

				function insertBbCode (bbCode)
				{
					if (!_isBbCodeView)
					{
						return
					}

					const bbCodeBox = getBbCodeBox()
					XF.insertIntoTextBox(bbCodeBox, bbCode)
				}

				function replaceBbCode (bbCode)
				{
					if (!_isBbCodeView)
					{
						return
					}

					const bbCodeBox = getBbCodeBox()
					XF.replaceIntoTextBox(bbCodeBox, bbCode)
				}

				function getTextArea ()
				{
					return (_isBbCodeView ? getBbCodeBox() : null)
				}

				function _init ()
				{
					ed.events.on('buttons.refresh', function ()
					{
						return !_isBbCodeView
					})
				}

				return {
					_init,
					getBbCodeBox,
					toBbCode,
					isBbCodeView,
					getTextArea,
					insertBbCode,
					replaceBbCode,
					toHtml,
					toggle,
					getToggleableButtons,
				}
			}

			FroalaEditor.PLUGINS.contentPreview = function (ed)
			{
				let _isPreview = false

				function getButton ()
				{
					return ed.$tb.find('.fr-command[data-cmd=xfPreview]')[0]
				}

				function getPreviewBox ()
				{
					const $outerEl = ed.$oel

					let previewBox = $outerEl.data('xfPreviewBox')
					if (!previewBox)
					{
						const computedStyle = window.getComputedStyle(ed.$el[0])
						const css = {
							paddingTop: computedStyle.paddingTop,
							paddingRight: computedStyle.paddingRight,
							paddingBottom: computedStyle.paddingBottom,
							paddingLeft: computedStyle.paddingLeft,
							minHeight: ed.opts.heightMin ? ed.opts.heightMin + 'px' : null,
						}

						previewBox = XF.createElementFromString('<div class="xfPreview" style="display:none"></div>')
						Object.assign(previewBox.style, css)

						$outerEl.data('xfPreviewBox', previewBox)

						ed.$wp.after(previewBox)
					}

					return previewBox
				}

				function btnsToDisable (button)
				{
					const allButtons = ed.$tb[0].querySelectorAll('.fr-btn-grp .fr-command')
					return Array.from(allButtons).filter(el => el !== button)
				}

				function toPreview (previewHtml)
				{
					const previewBox = getPreviewBox()

					const apply = function (previewHtml)
					{
						_isPreview = true

						let button

						ed.undo.saveStep()
						ed.$el.blur()

						// closes any active more toolbar
						ed.$tb.find('.fr-command.fr-open[data-cmd^="more"]').each(function ()
						{
							ed.commands.exec(this.getAttribute('data-cmd'))
						})

						button = getButton()

						const buttons = btnsToDisable(button)
						buttons.forEach(btn =>
						{
							btn.classList.add('fr-disabled', 'fr-invisible')
						})
						button.classList.add('fr-active')

						// switch tabs
						ed.$tb.find('.fr-btn-grp')
							.addClass('rte-tab--inactive')
							.filter('.rte-tab--preview').removeClass('rte-tab--inactive')

						// switch classes on outer box
						ed.$box.addClass('is-preview')

						if (ed.bbCode.isBbCodeView())
						{
							const box = ed.bbCode.getBbCodeBox()
							XF.display(box, 'none')
						}
						else
						{
							ed.$wp.css('display', 'none')
						}

						previewBox.innerHTML = ''
						previewBox.append(...previewHtml.querySelector('.bbWrapper').childNodes)
						XF.display(previewBox)
					}

					if (typeof previewHtml == 'string')
					{
						const range = document.createRange()
						const fragment = range.createContextualFragment(previewHtml)
						apply(fragment.firstChild)
					}
					else
					{
						// this is to force syncing the contents back to the textarea
						ed.events.trigger('form.submit')

						const form = ed.$oel.closest('form')[0]
						const href = ed.$oel.data('preview-url') ? ed.$oel.data('preview-url') : form.dataset.previewUrl
						const formData = XF.getDefaultFormData(form)

						XF.ajax(
							'POST',
							XF.canonicalizeUrl(href),
							formData,
							data =>
							{
								XF.setupHtmlInsert(data.html, html =>
								{
									XF.activate(html)
									apply(html)
								})
							},
						)
					}
				}

				function toHtml (skipFocus)
				{
					const previewBox = getPreviewBox()
					const button = getButton()
					const isBbCodeView = ed.bbCode.isBbCodeView()

					_isPreview = false

					// reset the buttons to the state that thre preview expects...
					const buttons = btnsToDisable(button).forEach(btn => btn.classList.remove('fr-disabled', 'fr-invisible'))
					if (button)
					{
						button.classList.remove('fr-active')
					}

					if (isBbCodeView)
					{
						// ... then restore the BB code view state if needed
						ed.bbCode.getToggleableButtons().forEach(btn => btn.classList.add('fr-disabled'))
					}

					// switch tabs
					ed.$tb.find('.fr-btn-grp')
						.removeClass('rte-tab--inactive')
						.filter('.rte-tab--preview').addClass('rte-tab--inactive')

					ed.$oel.removeAttr('disabled')
					XF.display(previewBox, 'none')

					// switch classes on outer box
					ed.$box.removeClass('is-preview')

					if (isBbCodeView)
					{
						const box = ed.bbCode.getBbCodeBox()
						XF.display(box)
					}
					else
					{
						ed.$wp.css('display', '')
					}

					if (!skipFocus)
					{
						ed.events.focus()
					}

					XF.layoutChange()
				}

				function toggle ()
				{
					const oel = ed.$oel[0]

					if (!XF.EditorHelpers.isPreviewAvailable(oel))
					{
						return
					}

					if (_isPreview)
					{
						toHtml()
					}
					else
					{
						XF.EditorHelpers.sync(ed)

						let testValue

						if (ed.bbCode && ed.bbCode.isBbCodeView())
						{
							testValue = ed.bbCode.getBbCodeBox().value
						}
						else
						{
							testValue = oel.value
						}

						if (!testValue)
						{
							return
						}

						toPreview()
					}
				}

				function isPreview ()
				{
					return _isPreview
				}

				function _init ()
				{
					ed.events.on('buttons.refresh', function ()
					{
						return !_isPreview
					})

					setupPreviewTabs()
					ed.events.on('codeView.toggle', function ()
					{
						setupPreviewTabs()
					})

					// turn the whole of the toolbar into a tab-switcher
					ed.$tb.on('click', function (e)
					{
						if (_isPreview)
						{
							if (!e.target.closest('.rte-tab--preview'))
							{
								toggle()
							}
						}
					})

					XF.on(ed.$tb[0].closest('form'), 'preview:hide', function ()
					{
						toHtml(true)
					})
				}

				function setupPreviewTabs ()
				{
					const $grps = ed.$tb.find('.fr-btn-grp')

					if (XF.EditorHelpers.isPreviewAvailable(ed.$oel[0]))
					{
						$grps.slice($grps.length - 1).addClass('rte-tab--inactive rte-tab--preview')
						$grps.slice($grps.length - 2, $grps.length - 1).addClass('rte-tab--beforePreview')
					}
					else
					{
						$grps.slice($grps.length - 1).addClass('rte-tab--beforePreview')
					}
				}

				return {
					_init,
					toPreview,
					isPreview,
					toHtml,
					toggle,
				}
			}

			for (cmd of Object.keys(this.commands))
			{
				FroalaEditor.DefineIcon(cmd, { NAME: this.commands[cmd][0] })
				FroalaEditor.RegisterCommand(cmd, this.commands[cmd][1])
			}
		},

		registerCustomCommands ()
		{
			let custom

			try
			{
				custom = JSON.parse(document.querySelector('.js-editorCustom').innerHTML) || {}
			}
			catch (e)
			{
				console.error(e)
				custom = {}
			}

			for (const tag of Object.keys(custom))
			{
				(function (tag, def)
				{
					// make sure this matches with the disabler in XF\Service\User\SignatureEdit
					const name = 'xfCustom_' + tag
					const tagUpper = tag.toUpperCase()
					let template = {}
					let faMatch

					if (def.type == 'fa')
					{
						faMatch = def.value.match(/^(?:(fa(?:[lrsdb]))\s)?fa-(.+)$/)
						if (faMatch)
						{
							const variant = faMatch[1] ?? 'fa'
							const name = faMatch[2]

							template = {
								FA5NAME: name,
								template: `${variant}_svg`,
							}
						}
						else
						{
							template = { NAME: def.value }
						}
					}
					else if (def.type == 'svg')
					{
						template = {
							template: 'svg',
							PATH: def.value,
						}
					}
					else if (def.type == 'image')
					{
						template = {
							template: 'image',
							SRC: '"' + XF.canonicalizeUrl(def.value) + '"',
							ALT: '"' + def.title + '"',
						}
					}

					const config = {
						title: def.title,
						icon: name,
						undo: true,
						focus: true,
						callback ()
						{
							XF.EditorHelpers.wrapSelectionText(
								this,
								def.option == 'yes' ? '[' + tagUpper + '=]' : '[' + tagUpper + ']',
								'[/' + tagUpper + ']',
								true,
							)
						},
					}

					FroalaEditor.DefineIcon(name, template)
					FroalaEditor.RegisterCommand(name, config)

					XF.editorStart.custom.push(name)
				})(tag, custom[tag])
			}

			// Now let's override a few icons
			FroalaEditor.DefineIcon('xfInsertGif', {
				template: 'svg',
				PATH: 'M11.5 9H13v6h-1.5zM9 9H6c-.6 0-1 .5-1 1v4c0 .5.4 1 1 1h3c.6 0 1-.5 1-1v-2H8.5v1.5h-2v-3H10V10c0-.5-.4-1-1-1zm10 1.5V9h-4.5v6H16v-2h2v-1.5h-2v-1z',
			})
			FroalaEditor.DefineIcon('textColor', { NAME: 'palette' }) // normally 'tint'
			FroalaEditor.DefineIcon('fontFamily', { NAME: 'font' }) // normally 'text'
			FroalaEditor.DefineIcon('fontSize', { NAME: 'text-size' }) // normally 'text-height'
			FroalaEditor.DefineIcon('tableColumns', { NAME: 'line-columns' }) // normally 'bars fa-rotate-90'
			FroalaEditor.DefineIcon('insertHR', { NAME: 'horizontal-rule' })
		},

		registerEditorDropdowns ()
		{
			let editorDropdowns

			try
			{
				editorDropdowns = JSON.parse(document.querySelector('.js-editorDropdowns').innerHTML) || {}
			}
			catch (e)
			{
				console.error('Editor dropdowns data not valid: ', e)
				editorDropdowns = {}
			}

			for (const cmd of Object.keys(editorDropdowns))
			{
				(function (cmd, button)
				{
					// removes the fa- prefix which we use internally
					button.icon = button.icon.substr(3)

					FroalaEditor.DefineIcon(cmd, { NAME: button.icon })
					FroalaEditor.RegisterCommand(cmd, {
						type: 'dropdown',
						title: button.title,
						icon: cmd,
						undo: false,
						focus: false,
						html ()
						{
							let o = '<ul class="fr-dropdown-list">'
							let options = button.buttons
							let c
							let info

							const editor = XF.getEditorInContainer(this.$oel[0])
							if (editor && editor.buttonManager)
							{
								// respect any removals if possible
								options = editor.buttonManager.getDropdown(cmd)
							}

							for (const i in options)
							{
								c = options[i]
								info = FroalaEditor.COMMANDS[c]
								if (info)
								{
									o += '<li><a class="fr-command" data-cmd="' + c + '">' + this.icon.create(info.icon || c) + '&nbsp;&nbsp;' + this.language.translate(info.title) + '</a></li>'
								}
							}
							o += '</ul>'

							return o
						},
					})
				})(cmd, editorDropdowns[cmd])
			}
		},

		registerDialogs ()
		{
			XF.EditorHelpers.dialogs.media = new XF.EditorDialogMedia('media')
			XF.EditorHelpers.dialogs.spoiler = new XF.EditorDialogSpoiler('spoiler')
			XF.EditorHelpers.dialogs.code = new XF.EditorDialogCode('code')
		},
	}

	XF.on(document, 'editor:start', XF.editorStart.startAll, { once: true })

	XF.EditorPlaceholderClick = XF.Event.newHandler({
		eventNameSpace: 'XFEditorPlaceholderClick',
		options: {},

		edInitialized: false,

		init () {},

		click (e)
		{
			const target = this.target
			const t = this

			target.querySelector('.editorPlaceholder-editor').classList.remove('is-hidden')
			target.querySelector('.editorPlaceholder-placeholder').classList.add('is-hidden')

			const editor = XF.getEditorInContainer(target)
			if (editor instanceof XF.Editor)
			{
				if (this.edInitialized)
				{
					return
				}

				editor.startInit({
					beforeInit ()
					{
						t.edInitialized = true
					},
					afterInit (xfEd, froalaEd)
					{
						// initialized with a click so focus
						froalaEd.events.focus(true)

						if (XF.isIOS())
						{
							xfEd.scrollToCursor()
							xfEd.scrollToCursorAfterPendingResize()
						}

						if (froalaEd.opts.tooltips)
						{
							setTimeout(() =>
							{
								// hide any tooltips that appeared as a result of the editor loading
								// as clicks in the placeholder may place the cursor over a button
								// and trigger a tooltip.
								froalaEd.tooltip.hide()
							}, 30)
						}
					},
				})
			}
			else
			{
				if (editor instanceof Element)
				{
					editor.focus()
				}
			}
		},
	})

	XF.Event.register('click', 'editor-placeholder', 'XF.EditorPlaceholderClick')

	XF.Element.register('editor', 'XF.Editor')
})(window, document)
