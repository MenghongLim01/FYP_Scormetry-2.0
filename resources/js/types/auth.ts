export type User = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'teacher' | 'student';
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    actingRole?: 'admin' | 'teacher' | 'student' | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
