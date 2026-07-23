export default function ApplicationLogo({ className = '', ...props }) {
    return (
        <svg
            {...props}
            className={className}
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <rect width="48" height="48" rx="14" fill="#D85A38" />
            <path
                d="M14 30.5C14 22.5 19.5 16 28 16"
                stroke="#FDFBF7"
                strokeWidth="3.2"
                strokeLinecap="round"
            />
            <circle cx="31.5" cy="16.5" r="3.2" fill="#3C2A21" />
            <path
                d="M16 34h16"
                stroke="#E5C4B3"
                strokeWidth="3.2"
                strokeLinecap="round"
            />
            <circle cx="18" cy="24" r="2" fill="#FDFBF7" />
            <circle cx="24" cy="21" r="2" fill="#A84830" />
        </svg>
    );
}
