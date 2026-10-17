// eslint-disable-next-line no-redeclare
const XF = {}
window.XF = XF

;((window, document) =>
{
	'use strict'

	const docEl = document.documentElement,
		cookiePrefix = docEl.getAttribute('data-cookie-prefix') || '',
		app = docEl.getAttribute('data-app')

	docEl.addEventListener('error', e =>
	{
		const target = e.target,
			onerror = target.getAttribute('data-onerror')
		switch (onerror)
		{
			case 'hide':
				XF.display(target, 'none')
				break

			case 'hide-parent':
				XF.display(target.parentNode, 'none')
				break
		}
	}, true)

	function readCookie (name)
	{
		const expr = new RegExp('(^| )' + cookiePrefix + name + '=([^;]+)(;|$)'),
			cookie = expr.exec(document.cookie)

		return cookie ? decodeURIComponent(cookie[2]) : null
	}

	function insertCss (css)
	{
		const el = document.createElement('style')
		el.type = 'text/css'
		el.innerHTML = css
		document.head.appendChild(el)
	}

	let isReady = false
	const readyCallbacks = []

	XF.ready = (fn) =>
	{
		if (isReady)
		{
			setTimeout(fn, 0)
		}
		else
		{
			readyCallbacks.push(fn)
		}
	}

	document.addEventListener('DOMContentLoaded', () =>
	{
		isReady = true

		for (const fn of readyCallbacks)
		{
			setTimeout(fn, 0)
		}
	})

	XF.Feature = (() =>
	{
		const tests = {}

		tests.touchevents = () => ('ontouchstart' in window)

		tests.passiveeventlisteners = () =>
		{
			let passiveEventListeners = false

			try
			{
				const opts = Object.defineProperty({}, 'passive', {
					get: () =>
					{
						passiveEventListeners = true
						return undefined
					},
				})

				const noop = () =>
				{
				}
				window.addEventListener('test', noop, opts)
				window.removeEventListener('test', noop, opts)
			}
			catch (e)
			{
				// ignore
			}

			return passiveEventListeners
		}

		function getBody ()
		{
			let body = document.body
			if (!body)
			{
				body = document.createElement('body')
				body.dataset.fake = 'true'
				document.body = body
			}
			return body
		}

		function cleanBody ()
		{
			if (document.body.dataset.fake === 'true')
			{
				document.body.parentNode.removeChild(document.body)
			}
		}

		tests.hiddenscroll = () =>
		{
			const body = getBody()

			const div = document.createElement('div')
			div.style.width = '100px'
			div.style.height = '100px'
			div.style.overflow = 'scroll'
			div.style.position = 'absolute'
			div.style.top = '-9999px'

			body.appendChild(div)

			const hiddenscroll = (div.offsetWidth === div.clientWidth)

			div.parentNode.removeChild(div)
			cleanBody()

			return hiddenscroll
		}

		tests.overflowanchor = () => (
			'CSS' in window &&
			'supports' in window.CSS &&
			window.CSS.supports('overflow-anchor', 'auto')
		)

		tests.displaymodestandalone = () => (
			('standalone' in window.navigator && window.navigator.standalone)
			|| window.matchMedia('(display-mode: standalone)').matches
		)

		tests.flexgap = () =>
		{
			// inspired by https://ishadeed.com/article/flexbox-gap/
			let body = getBody(),
				div,
				size = 10,
				supported = false

			div = document.createElement('div')
			div.style.display = 'flex'
			div.style.flexDirection = 'column'
			div.style.rowGap = `${ size }px`
			div.appendChild(document.createElement('div'))
			div.appendChild(document.createElement('div'))

			body.appendChild(div)
			supported = div.scrollHeight === size
			div.parentNode.removeChild(div)
			cleanBody()

			return supported
		}

		const testResults = {}
		let firstRun = true

		function runTests ()
		{
			const classes = []
			let result
			for (const t of Object.keys(tests))
			{
				if (typeof testResults[t] !== 'undefined')
				{
					continue
				}

				// need to ensure we always just get a t/f response
				result = Boolean(tests[t]())
				classes.push('has-' + (!result ? 'no-' : '') + t)

				testResults[t] = result
			}

			applyClasses(classes)
		}

		function runTest (name, body)
		{
			const result = Boolean(body())
			applyClasses(['has-' + (!result ? 'no-' : '') + name])

			testResults[name] = result
		}

		function applyClasses (classes)
		{
			let className = docEl.className

			if (firstRun)
			{
				className = className.replace(/(^|\s)has-no-js($|\s)/, '$1has-js$2')
				firstRun = false
			}
			if (classes.length)
			{
				className += ' ' + classes.join(' ')
			}
			docEl.className = className
		}

		function has (name)
		{
			if (typeof testResults[name] === 'undefined')
			{
				console.error('Asked for unknown test results: ' + name)
				return false
			}
			else
			{
				return testResults[name]
			}
		}

		return {
			runTests,
			runTest,
			has,
		}
	})()

	XF.Feature.runTests()

	if (app === 'public')
	{
		// prevent page jumping from dismissed notices
		(() =>
		{
			const dismissedNoticeCookie = readCookie('notice_dismiss'),
				dismissedNotices = dismissedNoticeCookie ? dismissedNoticeCookie.split(',') : []
			const hideAdvancedCookieNotice = readCookie('consent') !== null,
				selectors = []

			for (let noticeId of dismissedNotices)
			{
				noticeId = parseInt(noticeId, 10)
				if (noticeId === -1)
				{
					selectors.push('.notice.notice--cookie[data-notice-id="' + noticeId + '"]')
				}
				else if (noticeId !== 0)
				{
					selectors.push('.notice[data-notice-id="' + noticeId + '"]')
				}
			}

			if (hideAdvancedCookieNotice)
			{
				insertCss('.notice.notice--cookieAdvanced[data-notice-id="-1"] { display: none }')
			}

			if (selectors.length)
			{
				insertCss(selectors.join(', ') + ' { display: none !important }')
			}
		})()
	}

	(() =>
	{
		const ua = navigator.userAgent.toLowerCase()
		let match,
			browser

		match = /trident\/.*rv:([0-9.]+)/.exec(ua)
		if (match)
		{
			browser = {
				browser: 'msie',
				version: parseFloat(match[1]),
			}
		}
		else
		{
			// this is different regexes as we need the particular order
			match = /(msie)[ /]([0-9.]+)/.exec(ua)
				|| /(edge)[ /]([0-9.]+)/.exec(ua)
				|| /(chrome)[ /]([0-9.]+)/.exec(ua)
				|| /(webkit)[ /]([0-9.]+)/.exec(ua)
				|| /(opera)(?:.*version|)[ /]([0-9.]+)/.exec(ua)
				|| (ua.indexOf('compatible') < 0 && /(mozilla)(?:.*? rv:([0-9.]+)|)/.exec(ua))
				|| []

			if (match[1] == 'webkit' && ua.indexOf('safari'))
			{
				let safariMatch = /version[ /]([0-9.]+)/.exec(ua)
				if (safariMatch)
				{
					match = [match[0], 'safari', safariMatch[1]]
				}
				else
				{
					safariMatch = / os ([0-9]+)_([0-9]+)/.exec(ua)
					if (safariMatch)
					{
						match = [match[0], 'safari', safariMatch[1] + '.' + safariMatch[2]]
					}
					else
					{
						// count it as Safari, but we don't know the version
						match = [match[0], 'safari', 0]
					}
				}
			}

			browser = {
				browser: match[1] || '',
				version: parseFloat(match[2]) || 0,
			}
		}

		if (browser.browser)
		{
			browser[browser.browser] = true
		}

		let os = '',
			osVersion = null,
			osMatch

		if (/(ipad|iphone|ipod)/.test(ua))
		{
			os = 'ios'
			if ((osMatch = /os ([0-9_]+)/.exec(ua)))
			{
				osVersion = parseFloat(osMatch[1].replace('_', '.'))
			}
		}
		else if ((osMatch = /android[ /]([0-9.]+)/.exec(ua)))
		{
			os = 'android'
			osVersion = parseFloat(osMatch[1])
		}
		else if (/windows /.test(ua))
		{
			os = 'windows'
		}
		else if (/linux/.test(ua))
		{
			os = 'linux'
		}
		else if (/mac os/.test(ua))
		{
			os = 'mac'

			if (navigator.maxTouchPoints > 1 && navigator.platform === 'MacIntel')
			{
				// If we have multi touch, this is actually iPad OS with "request desktop site" on.
				// We need to identify as iOS to trigger some awkward bug fixes. Note that this won't
				// report an OS version as that's not exposed.
				os = 'ios'
			}
		}

		browser.os = os
		browser.osVersion = osVersion
		if (os)
		{
			browser[os] = true
		}

		docEl.className += (browser.os ? ' has-os-' + browser.os : '')
			+ (browser.browser ? ' has-browser-' + browser.browser : '')

		XF.browser = browser
	})()
})(window, document)
