'use strict'

// ################################## CONSTANTS #################################

const CACHE_NAME = 'xf-offline'
const CACHE_ROUTE = 'index.php?sw/cache.json'
const OFFLINE_ROUTE = 'index.php?sw/offline'

const supportPreloading = false

// ############################### EVENT LISTENERS ##############################

self.addEventListener('install', event =>
{
	self.skipWaiting()

	event.waitUntil(createCache())
})

self.addEventListener('activate', event =>
{
	self.clients.claim()

	event.waitUntil(
		new Promise(resolve =>
		{
			if (self.registration.navigationPreload)
			{
				self.registration.navigationPreload[supportPreloading ? 'enable' : 'disable']()
			}

			resolve()
		}),
	)
})

self.addEventListener('message', event =>
{
	const clientId = event.source.id
	const message = event.data
	if (typeof message !== 'object' || message === null)
	{
		console.error('Invalid message:', message)
		return
	}

	recieveMessage(clientId, message.type, message.payload)
})

self.addEventListener('fetch', event =>
{
	const request = event.request
	const accept = request.headers.get('accept')

	if (
		request.mode !== 'navigate' ||
		request.method !== 'GET' ||
		(accept && !accept.includes('text/html'))
	)
	{
		return
	}

	// bypasses for: HTTP basic auth issues, file download issues (iOS), common ad blocker issues
	if (request.url.match(/\/admin\.php|\/install\/|\/download($|&|\?)|[/?]attachments\/|google-ad|adsense/))
	{
		if (supportPreloading && event.preloadResponse)
		{
			event.respondWith(event.preloadResponse)
		}

		return
	}

	const response = Promise.resolve(event.preloadResponse)
		.then(r => r || fetch(request))

	event.respondWith(
		response
			.catch(error => caches.open(getCacheName())
				.then(cache => cache.match(OFFLINE_ROUTE))),
	)
})

self.addEventListener('push', event =>
{
	if (!(self.Notification && self.Notification.permission === 'granted'))
	{
		return
	}

	let data

	try
	{
		data = event.data.json()
	}
	catch (e)
	{
		console.warn('Received push notification but payload not in the expected format.', e)
		console.warn('Received data:', event.data.text())
		return
	}

	if (!data || !data.title || !data.body)
	{
		console.warn('Received push notification but no payload data or required fields missing.', data)
		return
	}

	const options = {
		body: data.body,
		dir: data.dir || 'ltr',
		data: data,
	}
	if (data.badge)
	{
		options.badge = data.badge
	}
	if (data.icon)
	{
		options.icon = data.icon
	}

	const notificationPromise = self.registration.showNotification(data.title, options)

	if ('setAppBadge' in self.navigator && 'clearAppBadge' in self.navigator)
	{
		let newCount = parseInt(String(data.total_unread).replace(/[,. ]/g, ''))

		if (newCount)
		{
			self.navigator.setAppBadge(newCount)
		}
		else
		{
			self.navigator.clearAppBadge()
		}
	}

	event.waitUntil(notificationPromise)
})

self.addEventListener('notificationclick', event =>
{
	const notification = event.notification
	notification.close()

	const url = notification.data.url
	if (!url)
	{
		return
	}

	const urlToOpen = new URL(url, self.location.origin).href

	const promiseChain = clients
		.matchAll({
			type: 'window',
			includeUncontrolled: true,
		})
		.then(windowClients =>
		{
			let matchingClient = null

			for (const windowClient of windowClients)
			{
				if (windowClient.url === urlToOpen && 'navigate' in windowClient)
				{
					matchingClient = windowClient
					break
				}
			}

			if (matchingClient)
			{
				return matchingClient.navigate(urlToOpen).then(client =>
				{
					if (client)
					{
						client.focus()
					}
					else
					{
						return clients.openWindow(urlToOpen)
					}
				})
			}
			else
			{
				return clients.openWindow(urlToOpen)
			}
		})

	event.waitUntil(promiseChain)
})

// ################################## MESSAGING #################################

function sendMessage (clientId, type, payload)
{
	if (typeof type !== 'string' || type === '')
	{
		console.error('Invalid message type:', type)
		return
	}

	if (typeof payload === 'undefined')
	{
		payload = {}
	}
	else if (typeof payload !== 'object' || payload === null)
	{
		console.error('Invalid message payload:', payload)
		return
	}

	clients.get(clientId)
		.then(client =>
		{
			client.postMessage({
				type: type,
				payload: payload,
			})
		})
		.catch(error =>
		{
			console.error('An error occurred while sending a message:', error)
		})
}

const messageHandlers = {}

function recieveMessage (clientId, type, payload)
{
	if (typeof type !== 'string' || type === '')
	{
		console.error('Invalid message type:', type)
		return
	}

	if (typeof payload !== 'object' || payload === null)
	{
		console.error('Invalid message payload:', payload)
		return
	}

	const handler = messageHandlers[type]
	if (typeof handler === 'undefined')
	{
		console.error('No handler available for message type:', type)
		return
	}

	handler(clientId, payload)
}

// ################################### CACHING ##################################

function getCacheName ()
{
	const match = self.location.pathname.match(/^\/(.*)\/[^/]+$/)
	let cacheModifier
	if (match && match[1].length)
	{
		cacheModifier = match[1].replace(/[^a-zA-Z0-9_-]/g, '')
	}
	else
	{
		cacheModifier = ''
	}

	return CACHE_NAME + (cacheModifier.length ? '-' : '') + cacheModifier
}

function createCache ()
{
	const cacheName = getCacheName()

	return caches.delete(cacheName)
		.then(() => caches.open(cacheName))
		.then(cache => fetch(CACHE_ROUTE)
			.then(response => response.json())
			.then(response =>
			{
				const key = response.key || null
				const files = response.files || []
				files.push(OFFLINE_ROUTE)

				return cache.addAll(files)
					.then(() => key)
			}))
		.catch(error =>
		{
			console.error('There was an error setting up the cache:', error)
		})
}

function updateCacheKey (clientId, key)
{
	sendMessage(clientId, 'updateCacheKey', { 'key': key })
}

messageHandlers.updateCache = (clientId, payload) =>
{
	createCache()
}
