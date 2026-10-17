((window, document) =>
{
	'use strict'

	XF.ApprovalControlClick = XF.Event.newHandler({
		eventType: 'click',
		eventNameSpace: 'XFApprovalControlClick',
		options: {
			container: '.approvalQueue-item',
		},

		item: null,
		value: null,

		init ()
		{
			this.item = this.target.closest(this.options.container)
			this.value = this.target.value
		},

		click (e)
		{
			this.item.classList.toggle('approvalQueue-item--approve', this.value === 'approve')
			this.item.classList.toggle('approvalQueue-item--delete', this.value === 'delete' || this.value === 'reject')
			this.item.classList.toggle('approvalQueue-item--spam', this.value === 'spam_clean')
		},
	})

	XF.Event.register('click', 'approval-control', 'XF.ApprovalControlClick')
})(window, document)
