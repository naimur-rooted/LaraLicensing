import { createFileRoute, redirect } from "@tanstack/react-router";

export const Route = createFileRoute("/auth/sign-up")({
  beforeLoad: ({ search }) => {
    throw redirect({
      to: "/register",
      search,
    });
  },
  component: () => null,
});
