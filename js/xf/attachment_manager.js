((window, document) =>
{
	'use strict'

	XF.AttachmentManager = XF.Element.newHandler({

		options: {
			uploadButton: '.js-attachmentUpload',
			manageUrl: null,
			container: '.js-attachmentUploads',
			filesContainer: '.js-attachmentFiles',
			fileRow: '.js-attachmentFile',
			insertMultiRow: '.js-attachmentInsertMultiRow',
			insertRow: '.js-attachmentInsert',
			selectToggleButton: '.js-attachmentSelect',
			selectActionButton: '.js-attachmentSelectAction',
			actionButton: '.js-attachmentAction',
			uploadTemplate: '.js-attachmentUploadTemplate',
			templateProgress: '.js-attachmentProgress',
			templateError: '.js-attachmentError',
			templateThumb: '.js-attachmentThumb',
			templateView: '.js-attachmentView',
			allowDrop: false,
			resizeImages: true,
            resizeImagesConvertType: true,
			maxImageWidth: null,
			maxImageHeight: null,
			checkVideoSize: true,
		},

		container: null,
		filesContainer: null,
		template: null,
		form: null,

		legacyMode: false,

		maxImageWidth: null,
		maxImageHeight: null,
		supportsVideoAudioUploads: null,

		manageUrl: null,
		flow: null,

		fileMap: {},
		exifMap: {},
		isUploading: false,
		lastScroll: 0,

		editor: null,

		init ()
		{
			const options = this.options
			const target = this.target

			if (!window.Flow)
			{
				console.error('flow.js must be loaded')
				return
			}

			const uploaders = target.querySelectorAll(options.uploadButton)

			if (this.options.manageUrl)
			{
				this.manageUrl = this.options.manageUrl
			}
			else
			{
				if (!uploaders.length)
				{
					console.error('No manage URL specified and no uploaders available.')
					return
				}

				const uploader = uploaders[0]
				this.manageUrl = uploader.dataset.uploadHref || uploader.getAttribute('href')
			}

			if (this.options.maxImageWidth || this.options.maxImageHeight)
			{
				this.maxImageWidth = this.options.maxImageWidth
				this.maxImageHeight = this.options.maxImageHeight
			}
			else
			{
				this.maxImageWidth = XF.config.uploadMaxWidth
				this.maxImageHeight = XF.config.uploadMaxHeight
			}

			this.container = target.querySelector(options.container)
			this.filesContainer = target.querySelector(options.filesContainer)

			if (this.container)
			{
				XF.onDelegated(this.container, 'click', options.actionButton, this.actionButtonClick.bind(this))
				XF.onDelegated(this.container, 'click', 'input[type="checkbox"]', this.checkboxClick.bind(this))
				XF.onDelegated(this.container, 'click', options.selectToggleButton, this.selectToggleClick.bind(this))
				XF.onDelegated(this.container, 'click', options.selectActionButton, this.selectActionClick.bind(this))
			}
			else
			{
				this.legacyMode = true

				XF.onDelegated(this.filesContainer, 'click', options.actionButton, this.actionButtonClick.bind(this))
			}

			this.template = target.querySelector(options.uploadTemplate).innerHTML
			if (!this.template)
			{
				console.error('No attached file template found.')
			}

			const flow = this.setupFlow()
			if (!flow)
			{
				console.error('No flow uploader support')
				return
			}

			this.flow = flow
			this.setupUploadButtons(uploaders, flow)

			if (this.options.allowDrop)
			{
				flow.assignDrop([target]) // extra array wrap due to flow.js bug
			}

			setTimeout(() =>
			{
				this.editor = XF.getEditorInContainer(this.target, '[data-attachment-target=false]')
				if (!this.editor)
				{
					this.removeInsertButtons(this.container)
				}

				this.toggleInsertMultiRow()
			}, 50)

			this.form = this.target.closest('form')
			if (this.form)
			{
				XF.on(this.form, 'ajax-submit:before', data =>
				{
					if (this.isUploading && !confirm(XF.phrase('files_being_uploaded_are_you_sure')))
					{
						data.preventSubmit = true
					}
				})

				XF.on(this.form, 'attachment-manager:reset', this.resetAttachments.bind(this))
			}
		},

		setupFlow ()
		{
			const options = this.getFlowOptions()
			const flow = new Flow(options)

			if (!flow.support)
			{
				return null
			}

			flow.on('fileAdded', this.fileAdded.bind(this))
			flow.on('filesSubmitted', this.filesSubmitted.bind(this))
			flow.on('fileProgress', this.uploadProgress.bind(this))
			flow.on('fileSuccess', this.uploadSuccess.bind(this))
			flow.on('fileError', this.uploadError.bind(this))

			return flow
		},

		getFlowOptions ()
		{
			return {
				target: this.manageUrl,
				allowDuplicateUploads: true,
				fileParameterName: 'upload',
				query: this.uploadQueryParams.bind(this),
				simultaneousUploads: 1,
				testChunks: false,
				progressCallbacksInterval: 100,
				chunkSize: 4 * 1024 * 1024 * 1024,
				// always one chunk
				readFileFn (fileObj, startByte, endByte, fileType, chunk)
				{
					let function_name = 'slice'

					if (fileObj.file.slice)
					{
						function_name = 'slice'
					}
					else if (fileObj.file.mozSlice)
					{
						function_name = 'mozSlice'
					}
					else if (fileObj.file.webkitSlice)
					{
						function_name = 'webkitSlice'
					}

					if (!fileType)
					{
						fileType = ''
					}

					chunk.readFinished(fileObj.file[function_name](startByte, endByte, fileType))
				},
			}
		},

		setupUploadButtons (uploaders, flow)
		{
			uploaders.forEach(button =>
			{
				let accept = button.dataset.accept || ''
				const target = XF.createElementFromString('<span class="js-attachButton"></span>')
				button.parentNode.insertBefore(target, button.nextSibling)
				target.appendChild(button)

				if (accept == '.')
				{
					accept = ''
				}

				XF.on(button, 'click', e => e.preventDefault())

				flow.assignBrowse(target, false, false, {
					accept,
				})

				if (this.supportsVideoAudioUploads === null)
				{
					const videoExtensions = XF.config.allowedVideoExtensions
					const audioExtensions = XF.config.allowedAudioExtensions
					const allowedExtensions = accept.split(',')

					for (const key in allowedExtensions)
					{
						const extension = allowedExtensions[key].substr(1)

						if (videoExtensions.includes(extension)
							|| audioExtensions.includes(extension)
						)
						{
							this.supportsVideoAudioUploads = true
							break
						}
					}
				}

				const file = target.querySelector('input[type=file]')
				file.setAttribute('title', XF.htmlspecialchars(XF.phrase('attach')))
				file.style.overflow = 'hidden'
				file.style[XF.isRtl() ? 'right' : 'left'] = -1000 + 'px'
			})
		},

		fileAdded (file)
		{
			const html = this.applyUploadTemplate({
				filename: file.name,
				uploading: true,
			})
			this.resizeProgress(html, 0)

			XF.DataStore.set(html, 'file', file)

			if (this.legacyMode)
			{
				this.filesContainer.classList.add('is-active')
			}
			else
			{
				this.container.classList.add('is-active')
			}

			this.filesContainer.appendChild(html)

			this.fileMap[file.uniqueIdentifier] = html

			const hScoller = this.filesContainer.closest('[data-xf-init="h-scroller"]')
			if (hScoller)
			{
				const hScroller = XF.Element.getHandler(hScoller, 'h-scroller')
				if (hScroller)
				{
					const now = Date.now()
					if (this.lastScroll < now - 500)
					{
						this.lastScroll = now
						hScroller.scrollTo(XF.position(html).left - 50)
					}
				}
			}

			this.target.querySelector(this.options.uploadButton).blur()

			const button = this.target.querySelector(this.options.uploadButton)
			const maxVideoSize = button.dataset.videoSize

			// avoid having to upload a huge file fully before being told it is too large
			if (this.options.checkVideoSize
				&& this.supportsVideoAudioUploads
				&& this.isVideoOrAudio(file)
				&& maxVideoSize > 0
				&& file.size > maxVideoSize
			)
			{
				// note: only applying this to videos as images at least can be made smaller after upload through resizing
				this.uploadError(file, this.addErrorToJson({}, XF.phrase('file_too_large_to_upload')))
				return false
			}
		},

		isVideoOrAudio (file)
		{
			const name = file.name
			const fileParts = name.split('.')
			const videoExtensions = XF.config.allowedVideoExtensions
			const audioExtensions = XF.config.allowedAudioExtensions
			let extension

			if (fileParts.length === 1 || (fileParts[0] === '' && fileParts.length))
			{
				return false
			}

			extension = fileParts.pop()

			return (videoExtensions.includes(extension)
				|| audioExtensions.includes(extension)
			)
		},

		async filesSubmitted (files)
		{
			await this.preProcessFiles(files)
			this.setUploading(true)
			this.flow.upload()
		},

		async preProcessFiles (files)
		{
			const preProcess = async (file) =>
			{
				try
				{
					const result = await this.preProcessFile(file)
					if (result === false)
					{
						throw new Error('File failed pre-processing')
					}
				}
				catch (e)
				{
					this.flow.removeFile(file)
				}
			}

			const preProcesses = []
			for (const file of files)
			{
				preProcesses.push(preProcess(file))
			}

			await Promise.allSettled(preProcesses)
		},

		async preProcessFile (fileObj)
		{
			if (
				typeof ExifReader !== 'undefined' &&
				fileObj.file.type.startsWith('image/')
			)
			{
				let exifData

				try
				{
					exifData = await ExifReader.load(fileObj.file)
				}
				catch (e)
				{
					exifData = null
				}

				if (exifData)
				{
					const exif = {}

					for (const tag of Object.values(exifData))
					{
						const id = tag.id
						if (!id)
						{
							continue
						}

						let value = tag.value
						if (Array.isArray(value))
						{
							if (value.length === 1)
							{
								value = value[0]
							}
							else if (value.length === 2)
							{
								value = value.join('/')
							}
							else
							{
								value = tag.description
							}
						}

						exif[id] = value
					}

					this.exifMap[fileObj.uniqueIdentifier] = exif
				}
			}

			if (
				this.options.resizeImages &&
				fileObj.file.type.startsWith('image/')
			)
			{
				try
				{
					const asType = this.options.resizeImagesConvertType && XF.config.imageOptimization === 'optimize'
						? 'image/webp'
						: null
					const file = await XF.ImageTools.resize(
						fileObj.file,
						this.maxImageWidth,
						this.maxImageHeight,
						asType
					)
					fileObj.file = file
					fileObj.name = file.fileName || file.name
					fileObj.size = file.size
					fileObj.relativePath = file.relativePath || file.webkitRelativePath || file.name
					fileObj.bootstrap()
				}
				catch (e)
				{
					// could not be resized, ignore
				}
			}

			// avoid having to upload a huge file fully before being told it is too large
			if (
				XF.config.uploadMaxFilesize > 0
				&& fileObj.size > XF.config.uploadMaxFilesize
			)
			{
				this.uploadError(fileObj, this.addErrorToJson(
					{},
					XF.phrase('uploaded_file_is_too_large_for_server_to_process'),
				))
				return false
			}
		},

		uploadProgress (file)
		{
			const html = this.fileMap[file.uniqueIdentifier]
			if (!html)
			{
				return
			}

			this.setUploading(true)

			this.resizeProgress(html, file.progress())
		},

		resizeProgress (row, progress)
		{
			const percent = Math.floor(progress * 100)
			const progressEl = row.querySelector(this.options.templateProgress)
			let inner = progressEl.querySelector('i')

			if (!inner)
			{
				progressEl.innerHTML = '&nbsp;'
				inner = XF.createElement('i', {}, progressEl)
			}

			inner.textContent = `${ percent }%`
			inner.style.width = `${ percent }%`
		},

		uploadSuccess (file, message, chunk)
		{
			let json = this.getObjectFromMessage(message)

			this.setUploading(false)
			delete this.exifMap[file.uniqueIdentifier]

			if (json.status && json.status == 'error')
			{
				this.uploadError(file, json, chunk)
				return
			}

			if (json.attachment)
			{
				this.insertUploadedRow(json.attachment, this.fileMap[file.uniqueIdentifier])
			}
			else
			{
				json = this.addErrorToJson(json)
				this.uploadError(file, json, chunk)
			}
		},

		setUploading (uploading)
		{
			const newValue = uploading ? true : false

			if (newValue !== this.isUploading)
			{
				this.isUploading = newValue

				if (newValue)
				{
					XF.trigger(this.target, 'attachment-manager:upload-start')
				}
				else
				{
					XF.trigger(this.target, 'attachment-manager:upload-end')
				}
			}
		},

		getObjectFromMessage (message)
		{
			if (message instanceof Object)
			{
				return message
			}

			try
			{
				return JSON.parse(message)
			}
			catch (e)
			{
				return this.addErrorToJson({})
			}
		},

		addErrorToJson (json, errorString)
		{
			json.status = 'error'
			json.errors = [errorString === null ? XF.phrase('oops_we_ran_into_some_problems') : errorString]

			return json
		},

		insertUploadedRow (attachment, existingHtml)
		{
			const newHtml = this.applyUploadTemplate(attachment)

			if (!this.editor)
			{
				this.removeInsertButtons(newHtml)
			}

			if (existingHtml)
			{
				existingHtml.replaceWith(newHtml)
			}
			else
			{
				if (this.legacyMode)
				{
					this.filesContainer.classList.add('is-active')
				}
				else
				{
					this.container.classList.add('is-active')
				}
				this.filesContainer.appendChild(newHtml)
			}

			XF.activate(newHtml)
			XF.layoutChange()

			XF.trigger(newHtml, XF.customEvent('attachment:row-inserted', {
				newHtml,
				attachmentManager: this,
			}))

			this.toggleInsertMultiRow()
		},

		uploadError (file, message, chunk)
		{
			const json = this.getObjectFromMessage(message)

			this.setUploading(false)
			delete this.exifMap[file.uniqueIdentifier]

			const row = this.fileMap[file.uniqueIdentifier]
			if (row && json.errors)
			{
				let error = json.errors[0]
				if (!error)
				{
					for (const k in json.errors)
					{
						error = json.errors[k]
						break
					}
				}

				row.querySelector(this.options.templateProgress).remove()
				row.querySelector(this.options.templateError).textContent = error
				row.classList.add('is-uploadError')

				delete this.fileMap[file.uniqueIdentifier]
				XF.DataStore.remove(row, 'file')
			}
			else
			{
				XF.defaultAjaxSuccessError(json, 200, chunk.xhr)
				this.removeFileRow(row)
			}
		},

		actionButtonClick (e)
		{
			e.preventDefault()

			const target = e.target.closest(this.options.actionButton)
			const action = target.getAttribute('data-action')
			const type = target.getAttribute('data-type')
			const row = target.closest(this.options.fileRow)

			switch (action)
			{
				case 'thumbnail':
				case 'full':
					this.insertAttachment(row, action, type)
					break

				case 'delete':
					this.deleteAttachment(row, type)
					break

				case 'cancel':
					this.cancelUpload(row)
					break
			}
		},

		checkboxClick ()
		{
			const checkedCount = this.filesContainer.querySelectorAll('input[type="checkbox"]:checked').length

			// disable action buttons if nothing is selected
			document.querySelectorAll(this.options.selectActionButton)
				.forEach(button => button.disabled = checkedCount ? false : true)
		},

		selectToggleClick (e)
		{
			e.preventDefault()

			this.setSelectActionState(!this.container.classList.contains('is-selecting'))

			e.target.blur()
		},

		setSelectActionState (onOff)
		{
			const container = this.container
			const current = container.classList.contains('is-selecting')
			if (current === onOff)
			{
				return
			}

			container.querySelectorAll(this.options.selectToggleButton).forEach(target =>
			{
				const toggleText = target.getAttribute('data-toggle')
				const text = target.textContent

				target.textContent = toggleText
				target.setAttribute('data-toggle', text)
			})

			container.classList[onOff ? 'add' : 'remove']('is-selecting')
		},

		selectActionClick (e)
		{
			e.preventDefault()

			const action = e.target.closest('button').getAttribute('data-action')
			const rowSelector = this.options.fileRow
			const buttonSelector = this.options.actionButton
			const checked = this.filesContainer.querySelectorAll(`${ rowSelector } input[type="checkbox"]:checked`)

			checked.forEach(_checked =>
			{
				const buttons = _checked.closest(rowSelector).querySelectorAll(buttonSelector)
				for (const button of buttons)
				{
					const type = button.dataset.type

					if ((type === 'video' || type === 'audio') && action === 'thumbnail')
					{
						if (button.dataset.action === 'full')
						{
							button.click()
							break
						}
					}
					else
					{
						if (button.dataset.action === action)
						{
							button.click()
							break
						}
					}
				}

				checked.forEach(checkbox => checkbox.checked = false)
			})

			this.container.querySelector(this.options.insertMultiRow)
				.querySelector('input[data-xf-init="check-all"]').checked = false

			this.setSelectActionState(false)
		},

		insertAttachment (row, action, type)
		{
			type = type || 'image'

			const attachmentId = row.dataset.attachmentId
			if (!attachmentId)
			{
				return
			}
			if (!this.editor)
			{
				return
			}

			const thumb = row.querySelector(this.options.templateThumb)?.getAttribute('src')
			const view = row.querySelector(this.options.templateView).getAttribute('href')

			let html
			let bbCode
			const params = {
				id: attachmentId,
				img: thumb,
			}

			if (type == 'video' || type == 'audio')
			{
				action = 'full'
			}

			if (action == 'full')
			{
				bbCode = `[ATTACH=full]${ attachmentId }[/ATTACH]`

				if (type == 'image')
				{
					html = '<img src="{{img}}" data-attachment="full:{{id}}" alt="" />'
				}
				else if (type == 'video')
				{
					html = '<span contenteditable="false" draggable="true" class="fr-video fr-dvi fr-draggable fr-deletable"><video data-xf-init="video-init" data-attachment="full:{{id}}" src="{{img}}" controls></video></span>'
				}
				else if (type == 'audio')
				{
					html = '<span contenteditable="false" draggable="true" class="fr-audio fr-dvi fr-draggable fr-deletable"><audio data-attachment="full:{{id}}" src="{{img}}" controls></audio></span>&nbsp;'
					// trailing nbsp is needed for audio as otherwise inserting audio back to back doesn't work correctly
				}

				params.img = view
			}
			else
			{
				if (!thumb || type !== 'image')
				{
					return
				}

				bbCode = `[ATTACH]${ attachmentId }[/ATTACH]`
				html = '<img src="{{img}}" data-attachment="thumb:{{id}}" alt="" />'
			}

			html = Mustache.render(html, params)
			XF.insertIntoEditor(this.target, html, bbCode, '[data-attachment-target=false]')
		},

		deleteAttachment (row, type)
		{
			type = type || 'image'

			const attachmentId = row.dataset.attachmentId
			if (!attachmentId)
			{
				return
			}

			XF.ajax(
				'post',
				this.manageUrl,
				{ delete: attachmentId },
				data =>
				{
					if (data.delete)
					{
						this.removeFileRow(row)
					}
				},
				{ skipDefaultSuccess: true },
			)

			const attrMatch = new RegExp('^[a-z]+:' + attachmentId + '$', 'i')
			const textMatch = new RegExp('\\[attach[^\\]]*\\]' + attachmentId + '\\[/attach\\]', 'gi')
			const htmlRemove = editor =>
			{
				editor.ed.$el.find('[data-attachment]')
					.filter(el => attrMatch.test(el.getAttribute('data-attachment')))
					.each((_, el) =>
					{
						if (type === 'image' || type === 'file')
						{
							editor.ed.image.remove(editor.ed.$(el))
						}
						else if (type === 'video' || type === 'audio')
						{
							el.parentNode.remove()
						}
					})
			}
			const bbCodeRemove = (textarea) =>
			{
				let val = textarea.value
				val = val.replace(textMatch, '')
				textarea.value = val
			}

			XF.modifyEditorContent(this.target, htmlRemove, bbCodeRemove, '[data-attachment-target=false]')
		},

		cancelUpload (row)
		{
			const file = XF.DataStore.get(row, 'file')
			const attachmentId = row.dataset.attachmentId

			if (attachmentId)
			{
				// fully uploaded and processed
				return
			}

			if (file && file.progress() == 1)
			{
				// fully uploaded and being processed, don't allow removal
				return
			}

			// cancel this file upload
			this.flow.removeFile(file)

			if (!this.flow.isUploading())
			{
				this.setUploading(false)
			}

			// this is either being uploaded or it has errored
			this.removeFileRow(row)
		},

		uploadQueryParams (fileObj)
		{
			const params = {
				_xfToken: XF.config.csrf,
				_xfResponseType: 'json',
				_xfWithData: 1,
			}

			if (this.exifMap[fileObj.uniqueIdentifier] !== undefined)
			{
				params['_xfExif'] = JSON.stringify(
					this.exifMap[fileObj.uniqueIdentifier]
				)
			}

			return params
		},

		applyUploadTemplate (params)
		{
			return XF.createElementFromString(Mustache.render(this.template, params).trim())
		},

		removeFileRow (row)
		{
			row.remove()

			this.toggleInsertMultiRow()

			if (!this.getFileRows().length)
			{
				if (this.legacyMode)
				{
					this.filesContainer.classList.remove('is-active')
				}
				else
				{
					this.container.classList.remove('is-active')
				}
				XF.layoutChange()
			}
		},

		removeInsertButtons (container)
		{
			container?.querySelectorAll(`${ this.options.insertRow }, ${ this.options.insertMultiRow }`).forEach(row => row.remove())

			XF.layoutChange()
		},

		toggleInsertMultiRow ()
		{
			this.checkboxClick()

			let rows = Array.from(this.filesContainer.querySelectorAll(this.options.actionButton))
				.filter(el => !el.dataset.action || el.dataset.action !== 'delete')
				.map(el => el.closest(this.options.fileRow))

			if (this.container)
			{
				let insertAllRow = this.container.querySelector(this.options.insertMultiRow)

				if (insertAllRow)
				{
					if (rows.length > 1)
					{
						insertAllRow.classList.add('is-active')
					}
					else
					{
						insertAllRow.classList.remove('is-active')
					}
				}
			}

			XF.layoutChange()
		},

		resetAttachments ()
		{
			this.getFileRows().forEach(row => this.removeFileRow(row))
		},

		getFileRows ()
		{
			return this.filesContainer.querySelectorAll(this.options.fileRow)
		},
	})

	XF.AttachmentOnInsert = XF.Element.newHandler({

		options: {
			fileRow: '.js-attachmentFile',
			href: null,
			linkData: null,
		},

		loading: false,

		init ()
		{
			const row = this.target.closest(this.options.fileRow)
			if (!row || !this.options.href)
			{
				console.error('Cannot find inserted row or action to perform.')
			}
			XF.on(row, 'attachment:row-inserted', this.onAttachmentInsert.bind(this))
		},

		onAttachmentInsert (e, html, manager)
		{
			if (this.loading)
			{
				return
			}

			const href = this.options.href
			const data = this.options.linkData || {}

			XF.ajax('post', href, data, this.onLoad.bind(this))
				.finally(() => this.loading = false)
		},

		onLoad (data)
		{
			if (!data.html)
			{
				return
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				this.target.replaceWith(html)
				XF.Animate.fadeDown(html, {
					speed: XF.config.speed.xfast,
					complete ()
					{
						onComplete(false, this.target)
						XF.layoutChange()
					},
				})
			})
		},
	})

	XF.ImageTools =
	{
		getQuality (file)
		{
			return XF.config.imageOptimizationQuality || 0.85
		},

		resize (file, maxWidth, maxHeight, asType)
		{
			return new Promise((resolve, reject) =>
			{
				if (!file.type.startsWith('image/'))
				{
					reject(new Error('The file is not an image.'))
					return
				}

				if (file.type === 'image/gif' || file.type === 'image/webp')
				{
					// browsers do not support animated GIFs or WebPs
					resolve(file)
					return
				}

				asType = asType || file.type

				const image = document.createElement('img')

				image.onload = () =>
				{
					let width = image.width
					let height = image.height

					let neededResizing = true
					if (
						(!maxWidth || (width <= maxWidth)) &&
						(!maxHeight || (height <= maxHeight))
					)
					{
						neededResizing = false
					}

					if (maxWidth && width > maxWidth)
					{
						height *= maxWidth / width
						width = maxWidth
					}
					if (maxHeight && height > maxHeight)
					{
						width *= maxHeight / height
						height = maxHeight
					}

					const canvas = document.createElement('canvas')
					const ctx = canvas.getContext('2d')
					if (ctx === null)
					{
						reject(new Error('Failed to retrieve a valid drawing context.'))
						return
					}

					canvas.width = width
					canvas.height = height

					ctx.drawImage(image, 0, 0, canvas.width, canvas.height)
					canvas.toBlob(
						(blob) =>
						{
							if (blob === null)
							{
								reject(new Error('Failed to create blob from the canvas.'))
								return
							}

							const newFile = new File([blob], file.name, {
								type: asType,
								lastModified: file.lastModified,
							})
							if (!neededResizing && newFile.size >= file.size)
							{
								resolve(file)
								return
							}

							resolve(newFile)
						},
						asType,
						this.getQuality(file),
					)

					URL.revokeObjectURL(image.src)
				}

				image.src = URL.createObjectURL(file)
			})
		},
	}

	XF.Element.register('attachment-manager', 'XF.AttachmentManager')
	XF.Element.register('attachment-on-insert', 'XF.AttachmentOnInsert')
})(window, document)
