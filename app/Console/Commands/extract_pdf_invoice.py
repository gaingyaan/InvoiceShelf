import sys
import json
import re

def extract_invoice_from_pdf(pdf_path):
    import pypdf
    reader = pypdf.PdfReader(pdf_path)
    full_text = "\n".join([page.extract_text() for page in reader.pages if page.extract_text()])

    data = {
        'invoice_number': '',
        'invoice_date': None,
        'due_date': None,
        'po_number': None,
        'terms': 'Net 15',
        'status': 'UNPAID',
        'currency': 'USD',
        'place_of_supply': '',
        'subject': '',
        'client_name': 'Client',
        'billing_address_1': '',
        'billing_address_2': '',
        'billing_city': '',
        'billing_state': '',
        'billing_country': 'India',
        'shipping_address_1': '',
        'shipping_address_2': '',
        'shipping_city': '',
        'shipping_country': 'India',
        'sub_total': 0.0,
        'tax': 0.0,
        'total': 0.0,
        'balance_due': 0.0,
        'notes': '',
        'bank_account_name': '',
        'bank_account_number': '',
        'bank_branch': '',
        'bank_ifsc': '',
        'bank_swift': '',
        'terms_conditions': '',
        'items': []
    }

    # Invoice Number (e.g. INV/GG/26-28/7)
    inv_match = re.search(r'[:#\s]*(INV/[A-Za-z0-9/\-_]+)', full_text, re.IGNORECASE)
    if inv_match:
        data['invoice_number'] = inv_match.group(1).strip()
    else:
        inv_match2 = re.search(r'(?:Invoice Number|Invoice #)\s*[:\s]*([A-Za-z0-9/\-_]+)', full_text, re.IGNORECASE)
        if inv_match2 and not re.match(r'^\d{2}/\d{2}/\d{4}', inv_match2.group(1).strip()):
            data['invoice_number'] = inv_match2.group(1).strip()

    # Invoice Date
    date_match = re.search(r'Invoice Date\s*[:\s]*([0-9]{1,2}[/\-\.][0-9]{1,2}[/\-\.][0-9]{2,4})', full_text)
    if date_match:
        parts = re.split(r'[/\-\.]', date_match.group(1).strip())
        if len(parts) == 3:
            day, month, year = parts
            if len(year) == 2: year = "20" + year
            data['invoice_date'] = f"{year}-{month.zfill(2)}-{day.zfill(2)}"

    # Due Date
    due_match = re.search(r'Due Date\s*[:\s]*([0-9]{1,2}[/\-\.][0-9]{1,2}[/\-\.][0-9]{2,4})', full_text)
    if due_match:
        parts = re.split(r'[/\-\.]', due_match.group(1).strip())
        if len(parts) == 3:
            day, month, year = parts
            if len(year) == 2: year = "20" + year
            data['due_date'] = f"{year}-{month.zfill(2)}-{day.zfill(2)}"

    # PO Number
    po_match = re.search(r'P\.?O\.?#?\s*[:\s]*([A-Za-z0-9/\-_]+)', full_text)
    if po_match:
        data['po_number'] = po_match.group(1).strip()

    # Terms
    terms_match = re.search(r'Terms\s*[:\s]*([A-Za-z0-9\s]+?)(?=\n|Due Date|$)', full_text)
    if terms_match:
        data['terms'] = terms_match.group(1).strip()

    # Place Of Supply
    pos_match = re.search(r'Place Of Supply\s*[:\s]*([^\n]+)', full_text)
    if pos_match:
        data['place_of_supply'] = pos_match.group(1).strip()

    # Subject
    subj_match = re.search(r'Subject\s*:\s*\n?([^\n]+)', full_text)
    if subj_match:
        data['subject'] = subj_match.group(1).strip()

    # Amounts
    amounts = [float(x.replace(',', '')) for x in re.findall(r'[\$€£₹]\s*([0-9,]+\.[0-9]{2})', full_text)]
    if not amounts:
        amounts = [float(x.replace(',', '')) for x in re.findall(r'\b([0-9,]+\.[0-9]{2})\b', full_text)]

    if amounts:
        data['total'] = amounts[0]
        data['sub_total'] = amounts[0]
        data['balance_due'] = amounts[-1] if len(amounts) > 1 else amounts[0]

    # Paid Status
    if 'PAID' in full_text or data['balance_due'] == 0:
        data['status'] = 'PAID'
        data['balance_due'] = 0.0
    else:
        data['status'] = 'UNPAID'

    # Bill To & Ship To
    if 'Bill To' in full_text:
        bts_part = full_text.split('Bill To', 1)[1]
        if 'Subject' in bts_part:
            addr_block = bts_part.split('Subject', 1)[0]
        else:
            addr_block = bts_part[:400]

        addr_lines = [l.strip() for l in addr_block.split('\n') if l.strip() and not l.strip().startswith('Ship To')]
        if len(addr_lines) >= 1:
            data['client_name'] = addr_lines[0]
        if len(addr_lines) >= 2:
            data['billing_address_1'] = addr_lines[1]
        if len(addr_lines) >= 3:
            data['billing_address_2'] = addr_lines[2]
        if len(addr_lines) >= 4:
            data['billing_city'] = addr_lines[3]

        # Check for shipping address lines
        for i, line in enumerate(addr_lines):
            if 'SHIIPING' in line or 'SHIPPING' in line:
                data['shipping_address_1'] = line
                if i + 1 < len(addr_lines):
                    data['shipping_address_2'] = addr_lines[i + 1]
                if i + 2 < len(addr_lines):
                    data['shipping_city'] = addr_lines[i + 2]
                break

    # Item Name & Description
    item_desc = "Agile Test Pro Consulting"
    item_hsn = "999293"
    item_name = "Project"

    item_match = re.search(r'([A-Za-z\s]+Consulting[^\n]*)\n\s*(\d{4,8})', full_text)
    if item_match:
        item_desc = item_match.group(1).strip()
        item_hsn = item_match.group(2).strip()

    if '\nProject\n' in full_text or full_text.endswith('\nProject'):
        item_name = "Project"

    data['items'].append({
        'name': item_name,
        'description': item_desc,
        'hsn_sac': item_hsn,
        'qty': 1.0,
        'rate': data['total'],
        'amount': data['total']
    })

    # Notes
    notes_match = re.search(r'Notes\s*\n([^\n]+(?:\n[^\n]+)?)', full_text)
    if notes_match:
        note_text = notes_match.group(1).strip()
        if 'Account Details:' in note_text:
            note_text = note_text.split('Account Details:')[0].strip()
        data['notes'] = note_text

    # Bank Details
    if 'Account Name:' in full_text:
        an = re.search(r'Account Name:\s*([^\n]+)', full_text)
        if an: data['bank_account_name'] = an.group(1).strip()
    if 'Account Number:' in full_text:
        num = re.search(r'Account Number:\s*([^\n]+)', full_text)
        if num: data['bank_account_number'] = num.group(1).strip()
    if 'Branch:' in full_text:
        br = re.search(r'Branch:\s*([^\n]+)', full_text)
        if br: data['bank_branch'] = br.group(1).strip()
    if 'IFSC:' in full_text:
        ifsc = re.search(r'IFSC:\s*([^\n]+)', full_text)
        if ifsc: data['bank_ifsc'] = ifsc.group(1).strip()
    if 'SWIFT:' in full_text:
        swift = re.search(r'SWIFT:\s*([^\n]+)', full_text)
        if swift: data['bank_swift'] = swift.group(1).strip()

    # Terms & Conditions
    if 'Terms & Conditions' in full_text:
        tc_part = full_text.split('Terms & Conditions', 1)[1]
        tc_lines = [l.strip() for l in tc_part.split('\n') if l.strip().startswith('-') or l.strip().startswith('•')]
        if tc_lines:
            data['terms_conditions'] = "\n".join(tc_lines)

    return data

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No file path provided'}))
        sys.exit(1)

    pdf_path = sys.argv[1]
    res = extract_invoice_from_pdf(pdf_path)
    print(json.dumps(res, ensure_ascii=False))
