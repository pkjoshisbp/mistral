# FAQ Upload Guide

## **How to Upload FAQs to the AI System**

### **Method 1: CSV Format (Recommended)**
**Best for:** Large number of FAQs, easy editing, structured data

**Structure:**
```csv
question,answer,category,priority
"Your question here","Your detailed answer here","category-name","high/medium/low"
```

**Example:**
```csv
question,answer,category
"What are your office hours?","We are open Monday to Friday from 9 AM to 6 PM","general"
"Do you accept credit cards?","Yes, we accept all major credit cards and digital payments","payment"
```

**Benefits:**
- Easy to edit in Excel/Google Sheets
- Supports categories and priorities
- Can handle hundreds of FAQs
- Automatically detected as FAQ format

---

### **Method 2: Text Format**
**Best for:** Simple Q&A, readable format

**Structure:**
```txt
Q: What services do you provide?
A: We provide comprehensive diagnostic services including blood tests and X-rays.

Q: What are your operating hours?
A: We are open Monday to Saturday from 8 AM to 8 PM.
```

**Alternative format:**
```txt
Frequently Asked Questions

Category: Services
Question: What services do you provide?
Answer: We provide comprehensive diagnostic services including blood tests and X-rays.

Category: Hours
Question: What are your operating hours?
Answer: We are open Monday to Saturday from 8 AM to 8 PM.
```

---

### **Method 3: Excel/XLSX Format**
**Best for:** Complex FAQs with multiple columns

**Columns:**
- A: Question
- B: Answer
- C: Category (optional)
- D: Priority (optional)
- E: Tags (optional)

---

### **Method 4: Word Document**
**Best for:** Formatted FAQs with sections

**Structure:**
```
FAQ - Diagnostic Center

Services Section:
Q: What tests do you offer?
A: We offer blood tests, X-rays, ultrasounds, and ECG.

Q: Do you provide home collection?
A: Yes, we provide home sample collection with additional charges.

Timing Section:
Q: What are your hours?
A: Monday to Saturday, 8 AM to 8 PM.
```

---

## **Upload Process:**

1. **Go to Admin Panel → Documents**
2. **Select Organization** (e.g., Gupta Diagnostics)
3. **Choose your FAQ file** (CSV, TXT, XLSX, or DOCX)
4. **Click "Upload & Process"**
5. **The system will:**
   - Extract questions and answers
   - Add them to the AI knowledge base
   - Make them available for chat responses

---

## **Sample Files Created:**

I've created sample files for you:
- `/var/www/clients/client1/web64/web/sample_files/diagnostic_center_faq.csv`
- `/var/www/clients/client1/web64/web/sample_files/diagnostic_center_faq.txt`

You can download these, modify them with your actual FAQs, and upload them.

---

## **Tips for Best Results:**

1. **Be Specific:** Include detailed answers with specific information
2. **Use Categories:** Group related questions (services, pricing, hours, etc.)
3. **Natural Language:** Write questions as customers would ask them
4. **Include Variations:** Add common variations of the same question
5. **Keep Updated:** Regularly update FAQs with new information

---

## **What Happens After Upload:**

- FAQs are processed and added to the organization's vector database
- The AI can now answer customer questions based on your FAQs
- Customers get accurate, organization-specific responses
- The system learns from the uploaded content to provide better answers
