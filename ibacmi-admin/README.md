# IBACMI Admin Interface

## Overview
The IBACMI Admin Interface is a web application designed for managing student documents and related functionalities. This project includes various components, styles, and scripts to provide a seamless experience for administrators.

## Project Structure
```
ibacmi-admin
├── components
│   └── sidebar.php          # Reusable sidebar component for consistent navigation
├── css
│   └── completedoc.css      # CSS styles for the Complete Documents section
├── js
│   └── complete-documents.js # JavaScript functionality for the Complete Documents page
├── pages
│   └── completedoc.php      # Implementation of the Complete Documents page
├── db_connect.php           # Database connection handling
└── README.md                # Project documentation
```

## Setup Instructions
1. **Clone the Repository**
   ```bash
   git clone <repository-url>
   cd ibacmi-admin
   ```

2. **Install Dependencies**
   Ensure you have a local server environment set up (e.g., XAMPP, MAMP) and place the project folder in the server's root directory.

3. **Database Configuration**
   - Create a database for the application.
   - Update the `db_connect.php` file with your database credentials.

4. **Access the Application**
   Open your web browser and navigate to `http://localhost/ibacmi-admin/pages/completedoc.php` to view the Complete Documents page.

## Usage Guidelines
- The sidebar component is included in all admin pages to ensure consistent navigation.
- Use the search functionality on the Complete Documents page to filter students based on their details.
- The application is designed to be responsive and should work on various screen sizes.

## Contributing
Contributions are welcome! Please fork the repository and submit a pull request for any enhancements or bug fixes.

## License
This project is licensed under the MIT License. See the LICENSE file for more details.