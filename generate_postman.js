const fs = require('fs');

// Helper function to create request
function createRequest(name, method, url, body = null, auth = false) {
    const request = {
        name: name,
        request: {
            method: method,
            header: [
                {
                    key: 'Content-Type',
                    value: 'application/json',
                    type: 'text'
                },
                {
                    key: 'Accept',
                    value: 'application/json',
                    type: 'text'
                }
            ],
            url: {
                raw: `{{base_url}}${url}`,
                host: ['{{base_url}}'],
                path: url.split('/').filter(p => p && !p.startsWith(':'))
            }
        }
    };

    // Add path variables
    const pathVars = url.match(/:\w+/g);
    if (pathVars) {
        request.request.url.variable = pathVars.map(v => ({
            key: v.substring(1),
            value: '',
            type: 'string'
        }));
    }

    if (auth) {
        request.request.header.push({
            key: 'Authorization',
            value: 'Bearer {{token}}',
            type: 'text'
        });
    }

    if (body) {
        request.request.body = {
            mode: 'raw',
            raw: JSON.stringify(body, null, 2),
            options: {
                raw: {
                    language: 'json'
                }
            }
        };
    }

    return request;
}

const collection = {
    info: {
        name: 'BOMEQP API Collection',
        description: 'Complete API collection for BOMEQP Accreditation Management System',
        schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
    },
    variable: [
        {
            key: 'base_url',
            value: 'http://localhost:8000/api',
            type: 'string'
        },
        {
            key: 'token',
            value: '',
            type: 'string'
        }
    ],
    item: [
        {
            name: 'Public Endpoints',
            item: [
                createRequest('Register User', 'POST', '/auth/register', {
                    name: 'John Doe',
                    email: 'john@example.com',
                    password: 'password123',
                    password_confirmation: 'password123',
                    role: 'training_center_admin'
                }),
                createRequest('Login', 'POST', '/auth/login', {
                    email: 'john@example.com',
                    password: 'password123'
                }),
                createRequest('Forgot Password', 'POST', '/auth/forgot-password', {
                    email: 'john@example.com'
                }),
                createRequest('Reset Password', 'POST', '/auth/reset-password', {
                    token: 'reset_token_here',
                    email: 'john@example.com',
                    password: 'newpassword123',
                    password_confirmation: 'newpassword123'
                }),
                createRequest('Verify Email', 'GET', '/auth/verify-email/:token'),
                createRequest('Verify Certificate', 'GET', '/certificates/verify/:code')
            ]
        },
        {
            name: 'Authentication',
            item: [
                createRequest('Get Profile', 'GET', '/auth/profile', null, true),
                createRequest('Update Profile', 'PUT', '/auth/profile', {
                    name: 'John Updated',
                    email: 'newemail@example.com'
                }, true),
                createRequest('Change Password', 'PUT', '/auth/change-password', {
                    current_password: 'oldpassword123',
                    password: 'newpassword123',
                    password_confirmation: 'newpassword123'
                }, true),
                createRequest('Logout', 'POST', '/auth/logout', null, true)
            ]
        },
        {
            name: 'Stripe Payments',
            item: [
                createRequest('Get Stripe Config', 'GET', '/stripe/config', null, true),
                createRequest('Create Payment Intent', 'POST', '/stripe/payment-intent', {
                    amount: 10000,
                    currency: 'usd',
                    description: 'Code purchase'
                }, true),
                createRequest('Confirm Payment', 'POST', '/stripe/confirm', {
                    payment_intent_id: 'pi_xxx',
                    transaction_id: 1
                }, true),
                createRequest('Refund Payment', 'POST', '/stripe/refund', {
                    payment_intent_id: 'pi_xxx',
                    amount: 1000
                }, true)
            ]
        },
        {
            name: 'Group Admin',
            item: [
                {
                    name: 'ACC Management',
                    item: [
                        createRequest('Get ACC Applications', 'GET', '/admin/accs/applications', null, true),
                        createRequest('Get ACC Application Details', 'GET', '/admin/accs/applications/:id', null, true),
                        createRequest('Approve ACC Application', 'PUT', '/admin/accs/applications/:id/approve', null, true),
                        createRequest('Reject ACC Application', 'PUT', '/admin/accs/applications/:id/reject', {
                            rejection_reason: 'Missing required documents'
                        }, true),
                        createRequest('Create ACC Space', 'POST', '/admin/accs/:id/create-space', null, true),
                        createRequest('Generate ACC Credentials', 'POST', '/admin/accs/:id/generate-credentials', null, true),
                        createRequest('List All ACCs', 'GET', '/admin/accs', null, true),
                        createRequest('Get ACC Details', 'GET', '/admin/accs/:id', null, true),
                        createRequest('Set Commission Percentage', 'PUT', '/admin/accs/:id/commission-percentage', {
                            commission_percentage: 15.5
                        }, true),
                        createRequest('Get ACC Transactions', 'GET', '/admin/accs/:id/transactions', null, true)
                    ]
                },
                {
                    name: 'Categories',
                    item: [
                        createRequest('Create Category', 'POST', '/admin/categories', {
                            name: 'Safety Training',
                            name_ar: 'تدريب السلامة',
                            description: 'Safety related courses',
                            status: 'active'
                        }, true),
                        createRequest('List Categories', 'GET', '/admin/categories', null, true),
                        createRequest('Update Category', 'PUT', '/admin/categories/:id', {
                            name: 'Updated Safety Training',
                            status: 'active'
                        }, true),
                        createRequest('Delete Category', 'DELETE', '/admin/categories/:id', null, true)
                    ]
                },
                {
                    name: 'Sub Categories',
                    item: [
                        createRequest('Create Sub Category', 'POST', '/admin/sub-categories', {
                            category_id: 1,
                            name: 'Occupational Safety',
                            name_ar: 'السلامة المهنية',
                            status: 'active'
                        }, true),
                        createRequest('List Sub Categories', 'GET', '/admin/sub-categories', null, true),
                        createRequest('Update Sub Category', 'PUT', '/admin/sub-categories/:id', {
                            name: 'Updated Sub Category'
                        }, true),
                        createRequest('Delete Sub Category', 'DELETE', '/admin/sub-categories/:id', null, true)
                    ]
                },
                {
                    name: 'Classes',
                    item: [
                        createRequest('Create Class', 'POST', '/admin/classes', {
                            course_id: 1,
                            name: 'Safety Fundamentals - Class 1'
                        }, true),
                        createRequest('List Classes', 'GET', '/admin/classes', null, true),
                        createRequest('Update Class', 'PUT', '/admin/classes/:id', {
                            name: 'Updated Class Name'
                        }, true),
                        createRequest('Delete Class', 'DELETE', '/admin/classes/:id', null, true)
                    ]
                },
                {
                    name: 'Financial & Reports',
                    item: [
                        createRequest('Get Financial Dashboard', 'GET', '/admin/financial/dashboard', null, true),
                        createRequest('Get Financial Transactions', 'GET', '/admin/financial/transactions', null, true),
                        createRequest('Get Settlements', 'GET', '/admin/financial/settlements', null, true),
                        createRequest('Request Payment from ACC', 'POST', '/admin/financial/settlements/:id/request-payment', null, true),
                        createRequest('Get Revenue Report', 'GET', '/admin/reports/revenue', null, true),
                        createRequest('Get ACCs Report', 'GET', '/admin/reports/accs', null, true),
                        createRequest('Get Training Centers Report', 'GET', '/admin/reports/training-centers', null, true),
                        createRequest('Get Certificates Report', 'GET', '/admin/reports/certificates', null, true)
                    ]
                },
                {
                    name: 'Stripe Settings',
                    item: [
                        createRequest('List Stripe Settings', 'GET', '/admin/stripe-settings', null, true),
                        createRequest('Get Active Stripe Setting', 'GET', '/admin/stripe-settings/active', null, true),
                        createRequest('Create Stripe Setting', 'POST', '/admin/stripe-settings', {
                            name: 'Production',
                            secret_key: 'sk_live_...',
                            publishable_key: 'pk_live_...',
                            currency: 'usd',
                            is_active: true
                        }, true),
                        createRequest('Update Stripe Setting', 'PUT', '/admin/stripe-settings/:id', {
                            name: 'Updated Name',
                            is_active: false
                        }, true),
                        createRequest('Delete Stripe Setting', 'DELETE', '/admin/stripe-settings/:id', null, true)
                    ]
                }
            ]
        },
        {
            name: 'ACC Admin',
            item: [
                {
                    name: 'Dashboard & Subscription',
                    item: [
                        createRequest('Get ACC Dashboard', 'GET', '/acc/dashboard', null, true),
                        createRequest('Get Subscription', 'GET', '/acc/subscription', null, true),
                        createRequest('Pay Subscription', 'POST', '/acc/subscription/payment', {
                            amount: 5000.00,
                            payment_method: 'credit_card'
                        }, true),
                        createRequest('Renew Subscription', 'PUT', '/acc/subscription/renew', {
                            auto_renew: true
                        }, true)
                    ]
                },
                {
                    name: 'Training Centers',
                    item: [
                        createRequest('Get Training Center Requests', 'GET', '/acc/training-centers/requests', null, true),
                        createRequest('Approve Training Center Request', 'PUT', '/acc/training-centers/requests/:id/approve', null, true),
                        createRequest('Reject Training Center Request', 'PUT', '/acc/training-centers/requests/:id/reject', {
                            rejection_reason: 'Insufficient documentation'
                        }, true),
                        createRequest('Return Training Center Request', 'PUT', '/acc/training-centers/requests/:id/return', {
                            return_comment: 'Please provide additional documents'
                        }, true),
                        createRequest('List Authorized Training Centers', 'GET', '/acc/training-centers', null, true)
                    ]
                },
                {
                    name: 'Instructors',
                    item: [
                        createRequest('Get Instructor Requests', 'GET', '/acc/instructors/requests', null, true),
                        createRequest('Approve Instructor Request', 'PUT', '/acc/instructors/requests/:id/approve', null, true),
                        createRequest('Reject Instructor Request', 'PUT', '/acc/instructors/requests/:id/reject', {
                            rejection_reason: 'Insufficient qualifications'
                        }, true),
                        createRequest('List Authorized Instructors', 'GET', '/acc/instructors', null, true)
                    ]
                },
                {
                    name: 'Courses',
                    item: [
                        createRequest('Create Course', 'POST', '/acc/courses', {
                            sub_category_id: 1,
                            name: 'Advanced Safety Training',
                            name_ar: 'تدريب السلامة المتقدم',
                            code: 'AST-101',
                            description: 'Comprehensive safety training course',
                            duration_hours: 40,
                            level: 'intermediate',
                            status: 'active'
                        }, true),
                        createRequest('List Courses', 'GET', '/acc/courses', null, true),
                        createRequest('Get Course Details', 'GET', '/acc/courses/:id', null, true),
                        createRequest('Update Course', 'PUT', '/acc/courses/:id', {
                            name: 'Updated Course Name'
                        }, true),
                        createRequest('Delete Course', 'DELETE', '/acc/courses/:id', null, true),
                        createRequest('Set Course Pricing', 'POST', '/acc/courses/:id/pricing', {
                            base_price: 500.00,
                            currency: 'USD',
                            group_commission_percentage: 10.0,
                            training_center_commission_percentage: 15.0,
                            instructor_commission_percentage: 5.0
                        }, true),
                        createRequest('Update Course Pricing', 'PUT', '/acc/courses/:id/pricing', {
                            base_price: 550.00
                        }, true)
                    ]
                },
                {
                    name: 'Certificate Templates',
                    item: [
                        createRequest('Create Template', 'POST', '/acc/certificate-templates', {
                            category_id: 1,
                            name: 'Safety Certificate Template',
                            template_html: '<html>...</html>',
                            status: 'active'
                        }, true),
                        createRequest('List Templates', 'GET', '/acc/certificate-templates', null, true),
                        createRequest('Get Template Details', 'GET', '/acc/certificate-templates/:id', null, true),
                        createRequest('Update Template', 'PUT', '/acc/certificate-templates/:id', {
                            name: 'Updated Template Name'
                        }, true),
                        createRequest('Delete Template', 'DELETE', '/acc/certificate-templates/:id', null, true),
                        createRequest('Preview Template', 'POST', '/acc/certificate-templates/:id/preview', {
                            sample_data: {
                                trainee_name: 'John Doe',
                                course_name: 'Advanced Safety Training'
                            }
                        }, true)
                    ]
                },
                {
                    name: 'Discount Codes',
                    item: [
                        createRequest('Create Discount Code', 'POST', '/acc/discount-codes', {
                            code: 'SAVE20',
                            discount_type: 'time_limited',
                            discount_percentage: 20.00,
                            applicable_course_ids: [1, 2, 3],
                            start_date: '2024-01-15',
                            end_date: '2024-02-15',
                            status: 'active'
                        }, true),
                        createRequest('List Discount Codes', 'GET', '/acc/discount-codes', null, true),
                        createRequest('Get Discount Code Details', 'GET', '/acc/discount-codes/:id', null, true),
                        createRequest('Update Discount Code', 'PUT', '/acc/discount-codes/:id', {
                            discount_percentage: 25.00
                        }, true),
                        createRequest('Delete Discount Code', 'DELETE', '/acc/discount-codes/:id', null, true),
                        createRequest('Validate Discount Code', 'POST', '/acc/discount-codes/validate', {
                            code: 'SAVE20',
                            course_id: 1
                        }, true)
                    ]
                },
                {
                    name: 'Materials',
                    item: [
                        createRequest('Create Material', 'POST', '/acc/materials', {
                            course_id: 1,
                            material_type: 'pdf',
                            name: 'Safety Manual',
                            description: 'Comprehensive safety manual',
                            price: 50.00,
                            file_url: '/materials/safety-manual.pdf',
                            status: 'active'
                        }, true),
                        createRequest('List Materials', 'GET', '/acc/materials', null, true),
                        createRequest('Get Material Details', 'GET', '/acc/materials/:id', null, true),
                        createRequest('Update Material', 'PUT', '/acc/materials/:id', {
                            name: 'Updated Material Name'
                        }, true),
                        createRequest('Delete Material', 'DELETE', '/acc/materials/:id', null, true)
                    ]
                },
                {
                    name: 'Certificates & Classes',
                    item: [
                        createRequest('List Certificates', 'GET', '/acc/certificates', null, true),
                        createRequest('List Classes', 'GET', '/acc/classes', null, true)
                    ]
                },
                {
                    name: 'Financial',
                    item: [
                        createRequest('Get Financial Transactions', 'GET', '/acc/financial/transactions', null, true),
                        createRequest('Get Settlements', 'GET', '/acc/financial/settlements', null, true)
                    ]
                }
            ]
        },
        {
            name: 'Training Center',
            item: [
                {
                    name: 'Dashboard',
                    item: [
                        createRequest('Get Training Center Dashboard', 'GET', '/training-center/dashboard', null, true)
                    ]
                },
                {
                    name: 'ACC Management',
                    item: [
                        createRequest('List Available ACCs', 'GET', '/training-center/accs', null, true),
                        createRequest('Request Authorization', 'POST', '/training-center/accs/:id/request-authorization', {
                            documents_json: [
                                { type: 'license', url: '/documents/license.pdf' }
                            ]
                        }, true),
                        createRequest('Get Authorization Status', 'GET', '/training-center/authorizations', null, true)
                    ]
                },
                {
                    name: 'Instructors',
                    item: [
                        createRequest('Create Instructor', 'POST', '/training-center/instructors', {
                            first_name: 'John',
                            last_name: 'Doe',
                            email: 'john@example.com',
                            phone: '+1234567890',
                            id_number: 'ID123456'
                        }, true),
                        createRequest('List Instructors', 'GET', '/training-center/instructors', null, true),
                        createRequest('Get Instructor Details', 'GET', '/training-center/instructors/:id', null, true),
                        createRequest('Update Instructor', 'PUT', '/training-center/instructors/:id', {
                            first_name: 'John Updated'
                        }, true),
                        createRequest('Delete Instructor', 'DELETE', '/training-center/instructors/:id', null, true),
                        createRequest('Request Instructor Authorization', 'POST', '/training-center/instructors/:id/request-authorization', {
                            acc_id: 1,
                            course_ids: [1, 2, 3]
                        }, true)
                    ]
                },
                {
                    name: 'Certificate Codes',
                    item: [
                        createRequest('Purchase Codes', 'POST', '/training-center/codes/purchase', {
                            acc_id: 1,
                            course_id: 1,
                            quantity: 10,
                            payment_method: 'wallet'
                        }, true),
                        createRequest('Get Code Inventory', 'GET', '/training-center/codes/inventory', null, true),
                        createRequest('Get Code Batches', 'GET', '/training-center/codes/batches', null, true)
                    ]
                },
                {
                    name: 'Wallet',
                    item: [
                        createRequest('Add Funds', 'POST', '/training-center/wallet/add-funds', {
                            amount: 1000.00,
                            payment_method: 'credit_card'
                        }, true),
                        createRequest('Get Wallet Balance', 'GET', '/training-center/wallet/balance', null, true),
                        createRequest('Get Wallet Transactions', 'GET', '/training-center/wallet/transactions', null, true)
                    ]
                },
                {
                    name: 'Classes',
                    item: [
                        createRequest('Create Class', 'POST', '/training-center/classes', {
                            course_id: 1,
                            class_id: 1,
                            instructor_id: 1,
                            start_date: '2024-02-01',
                            end_date: '2024-02-05',
                            max_capacity: 30,
                            location: 'physical'
                        }, true),
                        createRequest('List Classes', 'GET', '/training-center/classes', null, true),
                        createRequest('Get Class Details', 'GET', '/training-center/classes/:id', null, true),
                        createRequest('Update Class', 'PUT', '/training-center/classes/:id', {
                            max_capacity: 35
                        }, true),
                        createRequest('Delete Class', 'DELETE', '/training-center/classes/:id', null, true),
                        createRequest('Mark Class Complete', 'PUT', '/training-center/classes/:id/complete', null, true)
                    ]
                },
                {
                    name: 'Certificates',
                    item: [
                        createRequest('Generate Certificate', 'POST', '/training-center/certificates/generate', {
                            training_class_id: 1,
                            code_id: 1,
                            trainee_name: 'Jane Smith',
                            trainee_id_number: 'ID123456',
                            issue_date: '2024-02-05'
                        }, true),
                        createRequest('List Certificates', 'GET', '/training-center/certificates', null, true),
                        createRequest('Get Certificate Details', 'GET', '/training-center/certificates/:id', null, true)
                    ]
                },
                {
                    name: 'Marketplace',
                    item: [
                        createRequest('Browse Materials', 'GET', '/training-center/marketplace/materials', null, true),
                        createRequest('Get Material Details', 'GET', '/training-center/marketplace/materials/:id', null, true),
                        createRequest('Purchase from Marketplace', 'POST', '/training-center/marketplace/purchase', {
                            purchase_type: 'material',
                            item_id: 1,
                            acc_id: 1,
                            payment_method: 'wallet'
                        }, true),
                        createRequest('Get Library', 'GET', '/training-center/library', null, true)
                    ]
                }
            ]
        },
        {
            name: 'Instructor',
            item: [
                createRequest('Get Instructor Dashboard', 'GET', '/instructor/dashboard', null, true),
                createRequest('List Assigned Classes', 'GET', '/instructor/classes', null, true),
                createRequest('Get Class Details', 'GET', '/instructor/classes/:id', null, true),
                createRequest('Mark Class Complete', 'PUT', '/instructor/classes/:id/mark-complete', {
                    completion_rate_percentage: 95,
                    notes: 'Class completed successfully'
                }, true),
                createRequest('Get Available Materials', 'GET', '/instructor/materials', null, true),
                createRequest('Get Earnings', 'GET', '/instructor/earnings', null, true)
            ]
        }
    ]
};

// Write to file
fs.writeFileSync('BOMEQP_Postman_Collection.json', JSON.stringify(collection, null, 2));

console.log('Postman collection generated successfully!');
console.log('File: BOMEQP_Postman_Collection.json');
console.log('Import this file into Postman to use all APIs.');

