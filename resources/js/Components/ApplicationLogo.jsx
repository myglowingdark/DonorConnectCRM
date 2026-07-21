export default function ApplicationLogo({ className = 'h-10 w-10', alt = 'DonorConnect', ...props }) {
    return (
        <img
            src="/logo.png"
            alt={alt}
            className={className}
            {...props}
        />
    );
}
