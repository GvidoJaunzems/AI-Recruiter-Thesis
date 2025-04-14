# JobBoard Frontend

A modern React application for job seekers and employers to connect and manage job applications.

## Features

- User authentication (login/register)
- Job listing and search
- Job application submission
- User profile management
- Admin dashboard for job management
- Responsive design for all devices

## Prerequisites

- Node.js (v14 or higher)
- npm (v6 or higher)
- Backend API running on http://localhost:8000

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd frontend
```

2. Install dependencies:
```bash
npm install
```

3. Create a `.env` file in the root directory and add the following variables:
```
REACT_APP_API_URL=http://localhost:8000
REACT_APP_TITLE=JobBoard
```

## Development

To start the development server:

```bash
npm start
```

The application will be available at http://localhost:3000

## Building for Production

To create a production build:

```bash
npm run build
```

The build files will be created in the `build` directory.

## Project Structure

```
src/
  ├── components/     # Reusable UI components
  ├── contexts/      # React context providers
  ├── pages/         # Page components
  ├── App.js         # Main application component
  └── index.js       # Application entry point
```

## Available Scripts

- `npm start` - Runs the app in development mode
- `npm test` - Launches the test runner
- `npm run build` - Builds the app for production
- `npm run eject` - Ejects from Create React App

## Dependencies

- React
- React Router
- Material-UI
- Axios
- Formik (for form handling)
- Yup (for form validation)

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details. 