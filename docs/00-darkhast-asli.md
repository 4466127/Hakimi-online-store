# خواست اصلی صاحب پروژه — فروشگاه آنلاین حکیمی

> متن زیر همان چیزی است که صاحب پروژه در ۲۵ عقرب ۱۴۰۵ (۲۰۲۶-۱۱-۱۶) فرستاد، بدون تغییر. مرجع نیازهاست.
> در آخر، جمله‌ی اضافه‌ی صاحب پروژه به دری آمده است.

---

# Project: Hakimi Online Store
## Online E-commerce Platform for Afghanistan

I want to build a professional, modern, scalable e-commerce website for our family business in Afghanistan.

The main business goal is to increase sales by selling our existing physical store inventory online.

This is primarily an online sales platform, not another complex inventory or accounting management system.

## 1. Business Overview

We already have a physical store with sufficient product inventory.

We want to start selling online gradually, beginning with these product categories:

1. Shoes and boots.
2. Afghan traditional clothing and fashion.
3. Mobile phones and accessories.
4. Laptops and computer equipment.

The platform must allow us to introduce additional product categories in the future without changing the overall architecture.

Our initial target market is customers in Afghanistan.

## 2. Website Requirements

Design a modern, professional, mobile-first e-commerce website.

The initial version should include:

- Attractive homepage with promotional banners.
- Product categories and subcategories.
- Product listing and filtering.
- Search functionality.
- Detailed product pages with multiple images.
- Shopping cart.
- Customer registration and login.
- Checkout and order placement.
- Customer order tracking.
- Discount and promotional pricing.
- Related and recommended products.
- Contact and customer support information.

The interface must support Persian/Dari with complete RTL layout.

Use Afghan Afghani (AFN) as the default currency, with configurable support for additional currencies if required.

The architecture should allow Pashto and English to be added in future phases.

## 3. Product Management

Products must support different attributes depending on their category.

Examples:

Shoes and clothing:
- Size
- Color
- Material
- Available variants
- Stock per variant

Mobile phones:
- Brand
- Model
- Storage
- RAM
- Color
- Warranty
- Product condition

Laptops:
- Brand
- Processor
- RAM
- Storage
- Graphics
- Screen size
- Warranty
- Product condition

The administrator must be able to add products, upload images, change prices, manage stock, and activate or deactivate products.

Avoid hardcoding product categories or attributes.

## 4. Ordering and Delivery

Implement a simple and practical ordering workflow.

Order statuses should include:

- Pending
- Confirmed
- Processing
- Shipped
- Delivered
- Cancelled
- Returned

Customers should provide their name, phone number, province, city, address, and other necessary delivery information.

Delivery fees must be configurable by location.

Initially, support cash on delivery and manually confirmed payment methods.

Do not integrate a specific payment gateway until its availability and suitability for Afghanistan have been verified.

Keep payment methods configurable so online payment providers can be added later.

## 5. Integration with Existing Store Management System

We already have an existing store management application used by our family business.

Before designing the integration:

- Inspect its existing architecture and database structure, if access is provided.
- Determine whether it supports APIs or another safe integration method.
- Identify how product information, prices, stock, and sales are currently managed.
- Propose a reliable synchronization architecture.

Important:

Do not modify the existing operational management system.

Do not connect directly to its database or assume its architecture without inspection.

For the first release, an independent product and order management workflow is acceptable if integration is not yet practical.

Prevent overselling and stock inconsistencies.

## 6. Admin Dashboard

Create a practical administration panel for managing:

- Products and categories
- Product images and variants
- Prices and discounts
- Stock availability
- Customer accounts
- Orders
- Delivery settings
- Promotional banners
- Homepage content
- Sales reports

The admin interface should be simple enough for family members with limited technical experience.

## 7. Technology and Architecture

First inspect the available development environment and recommend an appropriate technology stack.

Prioritize:

- Security
- Performance
- Maintainability
- SEO
- Mobile responsiveness
- Easy deployment
- Scalability

Do not introduce unnecessary packages or services.

The website must be publicly accessible through the internet, unlike our local-network management applications.

## 8. Development Strategy

Develop the project incrementally.

Phase 1: Requirements analysis, architecture, database design, UI planning, and integration strategy.

Phase 2: Project foundation, database, authentication, and admin dashboard.

Phase 3: Product catalog, categories, images, variants, and search.

Phase 4: Shopping cart, checkout, and order management.

Phase 5: Delivery, payment methods, and customer notifications.

Phase 6: Existing store system integration, testing, security, SEO, and deployment.

Start with an MVP that can sell a limited number of real products. Do not attempt to implement every possible feature immediately.

## 9. Important Instructions

Before writing code:

1. Analyze the requirements.
2. Propose the application architecture.
3. Design the database.
4. Propose the UI structure.
5. Explain the integration strategy with the existing management system.
6. Identify infrastructure, hosting, security, and operational requirements.
7. Create CLAUDE.md, ARCHITECTURE.md, DATABASE.md, and ROADMAP.md.

Avoid unnecessary complexity and expensive external services.

Do not begin implementation until the architecture and initial roadmap are documented and presented for review.

The objective is to build a real, commercially usable online store that can start with a small product catalog and grow gradually with our business.

---

**جمله‌ی اضافه‌ی صاحب پروژه (دری):** «در ساخت وبسایت نباید تنها به PHP اکتفا کنیم؛ باید مثلاً برای امنیت و ظاهر زیبا از تکنالوژی‌های جدید هم استفاده شود، مثلاً Laravel یا هر تکنالوژی که خودت بهتر می‌دانی، برای responsive بودن با موبایل و …»
