# Boiler Services Plugin - Manual Testing Checklist

This document outlines the manual testing steps to verify the functionality of the Boiler Services plugin.

---

## 1. Initial Setup & Configuration

-   [ ] **1.1. Activate Plugin:** Activate the "Boiler Services Management" plugin.
-   [ ] **1.2. Create Service Categories:**
    -   [ ] Navigate to `Boiler Services > Service Categories`.
    -   [ ] Create at least two categories (e.g., `Installation`, `Repair`).
    -   [ ] *Expected: Categories appear in the list.*
-   [ ] **1.3. Create Boiler Types:**
    -   [ ] Navigate to `Boiler Services > Boiler Types`.
    -   [ ] Create at least two types (e.g., `Steam Boiler`, `Hot Water Boiler`).
    -   [ ] *Expected: Types appear in the list.*
-   [ ] **1.4. Create Services:**
    -   [ ] Navigate to `Boiler Services > Add New`.
    -   [ ] Create three different services, assigning them to the categories and types created above. (e.g., "Standard Steam Boiler Installation", "Emergency Hot Water Repair").
    -   [ ] *Expected: Services are created and visible under `Boiler Services > All Services` and on the `/services/` archive page.*
    -   `[Placeholder: Screenshot of the /services/ archive page]`

---

## 2. Expert User Flow

-   [ ] **2.1. Expert Registration:**
    -   [ ] As a logged-out user, navigate to the expert registration form page.
    -   [ ] Complete the multi-step form.
    -   [ ] **Test Resume:** Refresh the page midway through the form.
    -   [ ] *Expected: Form data is preserved after refresh.*
    -   [ ] Submit the final form.
    -   [ ] *Expected: A new user is created with the "Expert Technician" role and a status of "pending". An admin receives a notification.*
-   [ ] **2.2. Expert Approval (Admin):**
    -   [ ] Log in as an Administrator.
    -   [ ] Navigate to `Users`.
    -   [ ] Find the new "pending" expert.
    -   [ ] Use the row action to change the status to "Approved".
    -   [ ] Use the row action to set the grade to "A".
    -   [ ] *Expected: The user's status and grade are updated in the user list.*
    -   `[Placeholder: Screenshot of the admin users list with custom columns and actions]`
-   [ ] **2.3. Expert Login & Dashboard:**
    -   [ ] Log in as the newly approved expert.
    -   [ ] *Expected: User is redirected to the Expert Dashboard. The admin menu is minimal.*
    -   [ ] Check the dashboard for "New Opportunities" and "Notifications".
    -   [ ] *Expected: Both sections are initially empty.*

---

## 3. Customer & Service Request Flow

-   [ ] **3.1. Customer Registration:**
    -   [ ] Register a new user with the default "Subscriber" role (this will be our customer).
-   [ ] **3.2. Create Service Request (Fastest Expert):**
    -   [ ] Log in as the customer.
    -   [ ] Navigate to the service request form.
    -   [ ] Select a service category.
    -   [ ] Select a service.
    -   [ ] Set priority to `Fastest Expert`.
    -   [ ] Enter address details.
    -   [ ] **Test Resume:** Log out and log back in.
    -   [ ] *Expected: Form data is preserved.*
    -   [ ] Submit the request.
    -   [ ] *Expected: A new `service_request` is created with status `awaiting_experts`.*
-   [ ] **3.3. Expert Notification & Response:**
    -   [ ] Log in as the "Grade A" expert.
    -   [ ] *Expected: A notification appears in the dashboard about the new request.*
    -   [ ] From the dashboard, `Accept` the request.
    -   [ ] *Expected: The request card moves or disappears from "New Opportunities".*
    -   `[Placeholder: Screenshot of the Expert Dashboard with a new opportunity]`
-   [ ] **3.4. Customer Selection:**
    -   [ ] Log in as the customer.
    -   [ ] *Expected: A notification appears stating an expert has accepted.*
    -   [ ] On the customer dashboard, find the request and click "Select an Expert".
    -   [ ] Choose the expert from the list.
    -   [ ] *Expected: The request status changes to `assigned`.*
-   [ ] **3.5. Invoice & Payment (Simulated):**
    -   [ ] Log in as the expert.
    -   [ ] *Expected: The request now appears under "My Active Jobs". A notification confirms the assignment.*
    -   [ ] Click "Create Invoice" and enter an amount (e.g., 500).
    -   [ ] *Expected: An invoice is created. The expert's view updates.*
    -   [ ] Log in as the customer.
    -   [ ] *Expected: A notification appears with the invoice details and a "Pay Now" link.*
    -   [ ] (Simulate payment) Manually change the associated WooCommerce order to "Completed".
    -   [ ] *Expected: The `service_request` status changes to `completed`.*

---

## 4. Admin Management & Reporting

-   [ ] **4.1. View Management Table:**
    -   [ ] Log in as an Administrator.
    -   [ ] Navigate to `Service Requests > Manage & Reports`.
    -   [ ] *Expected: The list table displays all created service requests.*
-   [ ] **4.2. Test Filters:**
    -   [ ] Filter the list by status (e.g., `assigned`).
    -   [ ] *Expected: The table updates to show only matching requests.*
    -   [ ] Filter the list by a service category.
    -   [ ] *Expected: The table updates correctly.*
-   [ ] **4.3. Test Timeline View:**
    -   [ ] Find a completed request in the table.
    -   [ ] Click the "View Timeline" action link.
    -   [ ] *Expected: A modal appears showing the full history of the request (creation, status changes, expert selection, invoice creation).*
    -   `[Placeholder: Screenshot of the timeline modal]`
-   [ ] **4.4. Test CSV Export:**
    -   [ ] Apply a filter (e.g., by status).
    -   [ ] Click the "Export CSV" button.
    -   [ ] *Expected: A CSV file is downloaded containing only the filtered requests.*
    -   [ ] Open the CSV and verify its contents.

---

## 5. Security & Edge Cases

-   [ ] **5.1. Unauthorized Access:**
    -   [ ] As a logged-out user, attempt to access the expert dashboard URL directly.
    -   [ ] *Expected: Access is denied; user is redirected to login.*
    -   [ ] As a customer, attempt to access the expert dashboard URL.
    -   [ ] *Expected: A "permission denied" message is shown.*
-   [ ] **5.2. Data Isolation:**
    -   [ ] Create two customers and two experts.
    -   [ ] Have Customer 1 create a request.
    -   [ ] Log in as Customer 2.
    -   [ ] *Expected: Customer 2 cannot see or manage Customer 1's request.*
    -   [ ] Assign the request to Expert 1.
    -   [ ] Log in as Expert 2.
    -   [ ] *Expected: Expert 2 cannot see the assigned job in their "Active Jobs" list.*