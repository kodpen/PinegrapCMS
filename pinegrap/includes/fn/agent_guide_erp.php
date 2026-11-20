<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guide's erp topic (includes/fn/agent_guide.php): the accounting
 * module's records, which tools do which job, and the rules a write keeps.
 * The registry lists it only while the module is on. Tools are named as
 * {tool:route.id}, so a renamed or removed route fails
 * tests/agent_guide_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

function pg_agent_guide_erp_sections() {

	return array(

		array(
			'id'      => 'records',
			'heading' => 'What the accounting is made of',
			'lines'   => array(
				'Account: a customer or supplier card; balance is positive when it owes the store. {tool:erp.accounts.list}, its ledger {tool:erp.accounts.transactions}.',
				'Invoice: a sale, a purchase or a return. A draft has no number; issued, it is posted, moves stock and is never edited. {tool:erp.invoices.list}',
				'Quote and purchase order: priced offers that move no money, stock or tax. {tool:erp.quotes.list}, {tool:erp.purchase_orders.list}',
				'Waybill: a delivery note; goods, no money. {tool:erp.waybills.list}',
				'Receipt: money in, into a till and onto an account. Expense: a cost, paid now or later. {tool:erp.receipts.list}, {tool:erp.expenses.list}',
				'Till: a cash or bank account, with its live balance. {tool:erp.cash_accounts.list}',
				'Stock: counts and minimums of the products that track stock. {tool:erp.stock.levels}'
			)
		),

		array(
			'id'      => 'jobs',
			'heading' => 'Which tools do which job',
			'lines'   => array(
				'A summary of what is owed, late and due: {tool:erp.dashboard}.',
				'Find an account before opening one: {tool:erp.accounts.list} with tax_number; then {tool:erp.accounts.create}.',
				'Invoice: {tool:erp.invoices.create} saves a draft, {tool:erp.invoices.issue} numbers and posts it.',
				'Put an issued invoice right: {tool:erp.invoices.return} for some lines, {tool:erp.invoices.cancel} for all of it; a draft is deleted with {tool:erp.invoices.delete}.',
				'An order\'s invoice: {tool:erp.orders.invoice}, never typed in by hand. A quote\'s: {tool:erp.quotes.invoice}, then issue the draft.',
				'Money in: {tool:erp.receipts.create} with invoice_id to close that invoice. Paying an expense: {tool:erp.expenses.pay}.',
				'Shelf count: {tool:erp.stock_counts.create}, add items, then apply.',
				'Reports: {tool:erp.reports.vat}, {tool:erp.reports.profit}, {tool:erp.reports.aging}, {tool:erp.reports.cashflow}.'
			)
		),

		array(
			'id'      => 'writes',
			'heading' => 'Before a write',
			'lines'   => array(
				'Money is an integer in minor units; the ERP works out each line\'s discount, tax and withholding, so send quantities and unit prices, not totals.',
				'An invoice total is subtotal - discount + tax - withholding.',
				'Issuing, cancelling and returning take a number for good and cannot be undone: show the person the draft and wait for their yes.',
				'A tool that moves money (a receipt, a payment, a transfer, a cheque) runs only under its own name: dry_run true first, then ask.',
				'A date in a closed period is refused with period_locked; tell the person, never move the date to get past it.'
			)
		),

		array(
			'id'      => 'mistakes',
			'heading' => 'Frequent mistakes',
			'lines'   => array(
				'Opening a second card for a company: a tax_number on an active card is refused with the existing id.',
				'Posting the same money twice: after an error, look in {tool:erp.receipts.list} before sending a receipt again. Money past what an invoice is short of stays on the account.',
				'Expecting a draft in the invoice list: ask with status draft.'
			)
		)

	);

}
